<?php

namespace App\Services;

use App\Models\SubconAddProcessReport;
use App\Models\SubconApprovalLog;
use App\Models\SubconCuttingReport;
use App\Models\SubconJobLog;
use App\Models\SubconOfficialReport;
use App\Models\SubconOrder;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the Official Report (Berita Acara) — the per-Add-Process-
 * operation MD Production -> Director sign-off. See
 * docs/superpowers/specs/2026-09-22-subcon-official-report-design.md for the
 * full design; this class implements it.
 */
class SubconOfficialReportService
{
    public function __construct(
        private readonly SubconProductionService $production,
        private readonly D365JobTransactionService $d365,
        private readonly QcReportPdfService $reportPdf,
    ) {}

    /**
     * Order/Cut/Good/Reject qty for one Add Process operation on a
     * production group, summed across every line VSM's own production
     * detail has for the group. Deliberately duplicates the same math
     * `resources/views/subcon/admin/add-process/index.blade.php` already
     * computes inline for its Yield/TOTAL row, rather than refactoring that
     * already-working view to call a shared method — this keeps the
     * Official Report's snapshot logic independent of a view template, and
     * matches this codebase's existing convention of tolerating this kind
     * of small duplication over coupling unrelated things (see
     * AdminController/VendorController::saveItemRolls in CLAUDE.md).
     *
     * @return array{order_qty: int, cut_qty: int, good_qty: int, reject_qty: int}
     */
    public function computeYield(string $productionGroup, string $operation): array
    {
        $groups = $this->production->forProductionGroup($productionGroup);
        $lines = collect($groups)->flatMap(fn ($g) => collect($g['lines'] ?? []))->values();
        $cutQuantities = $this->production->cutQuantitiesForGroup($productionGroup);

        // cutQuantitiesForGroup() only has data once D365's own CMT-Cut job is
        // started/confirmed on the shop floor, which can lag well behind the
        // vendor's own cutting-report submission in the portal (same gap
        // SubconAddProcessController::index()'s Cut Qty column falls back
        // for). Without this, cut_qty silently computes as 0 here whenever
        // that D365 job hasn't started yet, understating allowed_limit in
        // computeDeduction() (floor(cutQty*0.01)) and so overcharging any
        // real reject qty. whereNull('add_process_operation') excludes an
        // Add Process PO flagged onto subcon_orders itself — see
        // SubconOrder::isAddProcess()'s docblock.
        $cmtOrder = SubconOrder::cmtOnly()->where('production_group', $productionGroup)
            ->first();
        if ($cmtOrder) {
            $localCutQuantities = SubconCuttingReport::where('order_id', $cmtOrder->id)
                ->get()
                ->keyBy('size')
                ->map(fn ($r) => ['good' => (float) $r->cutting_qty, 'reject' => 0.0])
                ->all();
            $cutQuantities = $cutQuantities + $localCutQuantities;
        }

        $reports = SubconAddProcessReport::where('production_group', $productionGroup)
            ->where('operation', $operation)
            ->get()
            ->keyBy('prod_id');

        return [
            'order_qty' => (int) $lines->sum('Qty'),
            'cut_qty' => (int) $lines->sum(fn ($l) => $cutQuantities[$l->Size]['good'] ?? 0),
            'good_qty' => (int) $lines->sum(fn ($l) => $reports->get($l->ProdId)->qty ?? 0),
            'reject_qty' => (int) $lines->sum(fn ($l) => $reports->get($l->ProdId)->reject_qty ?? 0),
        ];
    }

    /**
     * Per-size breakdown behind computeYield()'s totals — same data
     * sources, same "local save always wins, no live D365 prefill" rule
     * (this is what actually got frozen into the report's good_qty/
     * reject_qty columns at send time, so a row-by-row sum here always
     * matches those totals; the Add Process page's own live D365 prefill
     * is deliberately NOT mixed in here for that reason). Size only — no
     * PRD ID/Status columns, those are the Add Process page's own
     * production-detail concern, not this report's.
     *
     * @return array{rows: list<array{size: string, order_qty: int, cut_qty: int, good_qty: int, reject_qty: int, balance: int}>, total: array{order_qty: int, cut_qty: int, good_qty: int, reject_qty: int, balance: int}}
     */
    public function yieldMatrix(string $productionGroup, string $operation): array
    {
        $groups = $this->production->forProductionGroup($productionGroup);
        $lines = collect($groups)->flatMap(fn ($g) => collect($g['lines'] ?? []))->values();
        $cutQuantities = $this->production->cutQuantitiesForGroup($productionGroup);

        $cmtOrder = SubconOrder::cmtOnly()->where('production_group', $productionGroup)
            ->first();
        if ($cmtOrder) {
            $localCutQuantities = SubconCuttingReport::where('order_id', $cmtOrder->id)
                ->get()
                ->keyBy('size')
                ->map(fn ($r) => ['good' => (float) $r->cutting_qty, 'reject' => 0.0])
                ->all();
            $cutQuantities = $cutQuantities + $localCutQuantities;
        }

        $reports = SubconAddProcessReport::where('production_group', $productionGroup)
            ->where('operation', $operation)
            ->get()
            ->keyBy('prod_id');

        $rows = $lines->map(function ($l) use ($cutQuantities, $reports) {
            $cutQty = (int) ($cutQuantities[$l->Size]['good'] ?? 0);
            $good = (int) ($reports->get($l->ProdId)->qty ?? 0);
            $reject = (int) ($reports->get($l->ProdId)->reject_qty ?? 0);

            return [
                'size' => $l->Size,
                'order_qty' => (int) $l->Qty,
                'cut_qty' => $cutQty,
                'good_qty' => $good,
                'reject_qty' => $reject,
                'balance' => $cutQty - ($good + $reject),
            ];
        })->values()->all();

        $total = array_reduce($rows, function ($acc, $r) {
            $acc['order_qty'] += $r['order_qty'];
            $acc['cut_qty'] += $r['cut_qty'];
            $acc['good_qty'] += $r['good_qty'];
            $acc['reject_qty'] += $r['reject_qty'];
            $acc['balance'] += $r['balance'];

            return $acc;
        }, ['order_qty' => 0, 'cut_qty' => 0, 'good_qty' => 0, 'reject_qty' => 0, 'balance' => 0]);

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Per-operation port of QcReportPdfService::calculateDeductions()'s
     * formula shape (spec §8) — floor(cutQty*0.01) tolerance,
     * salesPrice*0.70 penalty price, charged only on the qty exceeding that
     * tolerance, plus a manual additional_deduction folded into the total.
     *
     * @return array{allowed_limit: int, exceeding_reject_qty: int, penalty_price: float, reject_penalty: float, total_deduction: float}
     */
    public function computeDeduction(int $cutQty, int $rejectQty, float $salesPrice, float $additionalDeduction = 0.0): array
    {
        $allowedLimit = (int) floor($cutQty * 0.01);
        $exceedingReject = max(0, $rejectQty - $allowedLimit);
        $penaltyPrice = $salesPrice * 0.70;
        $rejectPenalty = $exceedingReject * $penaltyPrice;

        return [
            'allowed_limit' => $allowedLimit,
            'exceeding_reject_qty' => $exceedingReject,
            'penalty_price' => $penaltyPrice,
            'reject_penalty' => $rejectPenalty,
            'total_deduction' => $rejectPenalty + $additionalDeduction,
        ];
    }

    /**
     * §5 of the spec: both checks must pass before a send/resend is allowed.
     * Re-run on every send/resend POST, not just hidden client-side.
     *
     * @return array{ok: bool, reason: ?string, cmt_order: ?SubconOrder, matched_po: ?array}
     */
    public function gate(string $productionGroup, string $operation): array
    {
        // Excludes an Add Process PO flagged onto subcon_orders itself (see
        // SubconOrder::isAddProcess()'s docblock) — this group can have one
        // of those alongside its real CMT order, and gating on the wrong
        // one's workflow_stage would misjudge whether cutting is approved.
        $cmtOrder = SubconOrder::cmtOnly()->where('production_group', $productionGroup)
            ->first();

        // Gated on actual cutting OUTPUT existing — the same cut_qty
        // computeYield()/the Add Process page itself already show, sourced
        // from D365's own CMT-Cut/IN-Cut job sync blended with any local
        // vendor-submitted cutting report — rather than on the CMT work
        // order's own in-app cutting approval having run. Approval and
        // physical cutting are frequently out of sync (the factory can cut
        // and move pieces into Washing/Embro well before anyone has
        // touched the portal's Cutting Entry form), and an Add Process
        // operation is its own independent commercial commitment (separate
        // PO/vendor) that has no actual data dependency on the CMT side's
        // approval state — only on cutting having genuinely happened.
        // Previously this checked $cmtOrder->workflow_stage against
        // STAGE_CUTTING/STAGE_CUTTING_REVIEW instead; changed 2026-10-26
        // because that blocked a confirmed, already-cut Add Process
        // operation purely on the CMT order's own approval paperwork
        // lagging behind, with no cut-qty dependency to justify it.
        //
        // Checked directly off cutQuantitiesForGroup()/the local cutting
        // report, NOT via computeYield()['cut_qty'] — that figure is summed
        // over forProductionGroup()'s per-size lines (production_group_lines),
        // which can legitimately be empty/not-yet-synced even when real cut
        // output already exists in job_transaction_line_details, silently
        // zeroing the check for no reason tied to cutting itself.
        $cutQuantities = $this->production->cutQuantitiesForGroup($productionGroup);
        if ($cmtOrder) {
            $localCutQuantities = SubconCuttingReport::where('order_id', $cmtOrder->id)
                ->get()
                ->keyBy('size')
                ->map(fn ($r) => ['good' => (float) $r->cutting_qty, 'reject' => 0.0])
                ->all();
            $cutQuantities = $cutQuantities + $localCutQuantities;
        }
        $hasCutQty = collect($cutQuantities)->contains(fn ($c) => ($c['good'] ?? 0) > 0);
        if (! $hasCutQty) {
            $reason = $cmtOrder
                ? 'No cutting output recorded yet for this work order.'
                : 'The D365 cut qty job sync is still blank for this in-house production group.';

            return ['ok' => false, 'reason' => $reason, 'cmt_order' => $cmtOrder, 'matched_po' => null];
        }

        $pos = $this->production->addProcessPurchaseOrdersForGroup($productionGroup);
        $matchedPo = $this->production->matchAddProcessPoForOperation($pos, $operation);

        if (! $matchedPo) {
            return ['ok' => false, 'reason' => "{$operation} has no confirmed purchase order yet.", 'cmt_order' => $cmtOrder, 'matched_po' => null];
        }

        if (strcasecmp($matchedPo['status'] ?? '', 'Invoiced') === 0) {
            return ['ok' => false, 'reason' => "{$operation}'s purchase order ({$matchedPo['po_number']}) is already invoiced — reporting is closed.", 'cmt_order' => $cmtOrder, 'matched_po' => $matchedPo];
        }

        return ['ok' => true, 'reason' => null, 'cmt_order' => $cmtOrder, 'matched_po' => $matchedPo];
    }

    /**
     * First send (no row exists yet) or resend (existing pending_ho row,
     * typically after a Director decline — revision_count is NOT
     * incremented here, only on decline; see applyDirectorDecline()).
     * Creates/updates the row directly in pending_ho and emails MD
     * Production. Throws \RuntimeException (message = gate()'s reason) when
     * blocked — callers turn that into a flash error.
     */
    public function send(string $productionGroup, string $operation, float $additionalDeduction, ?string $remarks, ?float $salesPriceOverride = null, ?string $approver = null): SubconOfficialReport
    {
        $gate = $this->gate($productionGroup, $operation);
        if (! $gate['ok']) {
            throw new \RuntimeException($gate['reason']);
        }

        $existing = SubconOfficialReport::where('production_group', $productionGroup)
            ->where('operation', $operation)
            ->first();
        if ($existing && ! $existing->isPendingHo()) {
            throw new \RuntimeException("{$operation} already has an Official Report in progress or completed — it can't be re-sent from here.");
        }

        $matchedPo = $gate['matched_po'];
        $yield = $this->computeYield($productionGroup, $operation);

        $salesPrice = $salesPriceOverride;
        $salesPriceSource = $salesPrice !== null ? 'manual' : null;
        if ($salesPrice === null) {
            // forProductionGroup() always hardcodes plm_id to '' (it has no
            // real PLM lookup of its own) — the real PLM link, when one
            // exists, lives in plm_activity, same source
            // addProcessPurchaseOrdersForGroup() already reads elsewhere in
            // SubconProductionService. Best-effort: a vsm outage or a
            // standalone/in-house group with no PLM link both fall through
            // to the existing "admin fills sales_price manually" path.
            $plmId = null;
            try {
                $plmId = DB::connection('vsm')->table('plm_activity')
                    ->where('ProductionGroup', $productionGroup)
                    ->value('PLMId');
            } catch (\Throwable $e) {
                report($e);
            }
            $salesPrice = $plmId ? $this->d365->fetchSalesPrice((string) $plmId) : null;
            $salesPriceSource = $salesPrice !== null ? 'd365' : null;
        }

        $deduction = $this->computeDeduction($yield['cut_qty'], $yield['reject_qty'], (float) ($salesPrice ?? 0), $additionalDeduction);

        // Whoever clicks "Send" here already has a subcon_admin portal
        // account — confirmed live that qc_ho_approver_email's full
        // recipient list does too — and is the one actually providing
        // additional_deduction/sales_price/remarks on this same form. A
        // separate MD Production approval click on the SAME figures moments
        // later is a rubber stamp, not a real second review, so send()
        // folds it in: passing $approver sends straight to pending_director
        // with the HO signature stamped immediately, skipping pending_ho
        // entirely. $approver is optional only so existing callers that
        // don't pass it (e.g. tests exercising sales_price resolution) keep
        // the old pending_ho behavior unchanged. The *revision* path
        // (applyHoApproval(), reached after a Director decline) is
        // deliberately untouched — that's a real second look at figures
        // someone else already saw once, not a rubber stamp.
        $isAutoApproved = $approver !== null;

        $report = SubconOfficialReport::updateOrCreate(
            ['production_group' => $productionGroup, 'operation' => $operation],
            [
                'status' => $isAutoApproved ? SubconOfficialReport::STATUS_PENDING_DIRECTOR : SubconOfficialReport::STATUS_PENDING_HO,
                'matched_po_number' => $matchedPo['po_number'] ?? null,
                'matched_po_vendor_code' => $matchedPo['vendor_code'] ?? null,
                'matched_po_vendor_name' => $matchedPo['vendor_name'] ?? null,
                'order_qty' => $yield['order_qty'],
                'cut_qty' => $yield['cut_qty'],
                'good_qty' => $yield['good_qty'],
                'reject_qty' => $yield['reject_qty'],
                'sales_price' => $salesPrice,
                'sales_price_source' => $salesPriceSource,
                'allowed_limit' => $deduction['allowed_limit'],
                'exceeding_reject_qty' => $deduction['exceeding_reject_qty'],
                'penalty_price' => $deduction['penalty_price'],
                'reject_penalty' => $deduction['reject_penalty'],
                'additional_deduction' => $additionalDeduction,
                'remarks' => $remarks,
                'director_decline_reason' => null,
                // Same 'Digitally Signed: <actor> [UTC+07:00: <timestamp>]'
                // contract as QcApprovalController's signatures (e.g.
                // hoApprove()'s $signature) — kept consistent so this
                // column renders identically wherever it's shown.
                'ho_approval_signature' => $isAutoApproved ? $this->signatureFor($approver) : null,
                'ho_approved_at' => $isAutoApproved ? now() : null,
                'director_approval_signature' => null,
                'director_approved_at' => null,
            ]
        );

        if ($isAutoApproved) {
            $this->logResult($report, 'ho_approved', "Sent and HO-approved by {$approver} (combined send + approval).");
        }

        return $report;
    }

    /**
     * Renders the Official Report PDF as a `data:application/pdf;base64,...`
     * URI (spec §9) — the shape RpaQueueService's offloadSignedDocToS3()
     * expects (matches QcReportPdfService::renderDataUri()'s contract).
     * Overview fields (article name / season / PLM id) come from
     * forProductionGroup()'s own group metadata, best-effort — a
     * standalone/PLM-less group simply renders '—' for those, same as the
     * rest of this feature treats a missing PLM link. forProductionGroup()
     * has no delivery-plan data source at all, so that field is always null
     * here (renders as '—') — a known, accepted limitation, not a bug.
     */
    public function renderPdfDataUri(SubconOfficialReport $report): ?string
    {
        try {
            $groups = $this->production->forProductionGroup($report->production_group);
            $first = $groups[0] ?? [];

            // forProductionGroup() always hardcodes plm_id to '' — the real
            // PLM link, when one exists, lives in plm_activity, same
            // best-effort lookup send() already does (see its $plmId).
            $plmId = null;
            try {
                $plmId = DB::connection('vsm')->table('plm_activity')
                    ->where('ProductionGroup', $report->production_group)
                    ->value('PLMId');
            } catch (\Throwable $e) {
                report($e);
            }

            // Same logo asset + data-URI pattern as QcReportPdfService's
            // inspection-report-body partial, so both PDFs carry the same
            // wordmark.
            $logoPath = public_path('images/mp-logo-report.png');
            $logoData = is_file($logoPath)
                ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
                : null;

            // Same stamp shape (badge + truncated email + date, name + role
            // below a divider) as the base CMT inspection report's
            // signature boxes — parseSignature() is the shared 'Digitally
            // Signed: <actor> [UTC+07:00: <ts>]' decoder both reports' HO/
            // Director columns are written in (SubconOfficialReportService::
            // signatureFor() / QcApprovalController's own signature lines).
            $signatures = [
                'ho' => $this->reportPdf->parseSignature((string) $report->ho_approval_signature),
                'director' => $this->reportPdf->parseSignature((string) $report->director_approval_signature),
            ];

            $bytes = \Barryvdh\DomPDF\Facade\Pdf::loadView('subcon.pdf.official-report', [
                'report' => $report,
                'articleName' => $first['article_name'] ?? null,
                'deliveryPlan' => null,
                'season' => $first['season'] ?? null,
                'plmId' => $plmId ?? ($first['plm_id'] ?? null),
                'logoData' => $logoData,
                'signatures' => $signatures,
                'yield' => $this->yieldMatrix($report->production_group, $report->operation),
            ])->setPaper('a4', 'portrait')->output();

            return 'data:application/pdf;base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Official Report PDF render failed', ['report' => $report->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * MD Production approves — first send or a post-decline resend (same
     * method either way, spec §6's "no HO-decline route" note). Deduction/
     * remarks are only overwritten when explicitly passed (the revision
     * case); this method never touches reject_penalty — it's unaffected by
     * additional_deduction. totalDeduction() is computed from the model's
     * current state, so it naturally reflects any new additional_deduction
     * once saved, without any recompute step here.
     */
    public function applyHoApproval(SubconOfficialReport $report, string $approver, ?float $additionalDeduction = null, ?string $remarks = null): void
    {
        if (! $report->isPendingHo()) {
            throw new \RuntimeException('This report is no longer awaiting MD Production approval.');
        }

        DB::transaction(function () use ($report, $approver, $additionalDeduction, $remarks) {
            if ($additionalDeduction !== null) {
                $report->additional_deduction = $additionalDeduction;
            }
            if ($remarks !== null) {
                $report->remarks = $remarks;
            }

            $report->ho_approval_signature = $this->signatureFor($approver);
            $report->ho_approved_at = now();
            $report->status = SubconOfficialReport::STATUS_PENDING_DIRECTOR;
            $report->save();
        });

        $this->logResult($report, 'ho_approved', "HO approved by {$approver}.");
    }

    /**
     * Director approves — terminal. Renders the PDF once (signature is now
     * complete on the HO side, still pending on the Director side... but the
     * approval write happens first so the rendered PDF reflects BOTH
     * signatures), then queues Invoice + Deduction RPA (spec §10). RPA
     * queueing is best-effort and never blocks the approval itself — a
     * queueing failure is logged, not thrown.
     */
    public function applyDirectorApproval(SubconOfficialReport $report, string $approver): void
    {
        if (! $report->isPendingDirector()) {
            throw new \RuntimeException('This report is no longer awaiting Director authorization.');
        }

        DB::transaction(function () use ($report, $approver) {
            $report->director_approval_signature = $this->signatureFor($approver);
            $report->director_approved_at = now();
            $report->status = SubconOfficialReport::STATUS_COMPLETED;
            $report->save();
        });

        $signedDoc = $this->renderPdfDataUri($report);
        if ($signedDoc !== null) {
            $rpaQueue = app(RpaQueueService::class);
            $rpaQueue->queueOfficialReportInvoice($report, $signedDoc);
            $rpaQueue->queueOfficialReportDeduction($report, $signedDoc);
        } else {
            \Illuminate\Support\Facades\Log::warning("Official Report {$report->id}: director approved but PDF render failed — RPA not queued.");
        }

        $this->logResult($report, 'director_approved', "Director approved by {$approver}.");
        $this->logDirectorDecision($report, 'approved', $approver, "Director authorized Add Process ({$report->operation}); Invoice/Deduction RPA queued; report completed.");
    }

    /**
     * 'Digitally Signed: <actor> [UTC+07:00: <timestamp>]' — same contract
     * QcApprovalController's signature columns use (e.g. hoApprove()'s
     * $signature), so this report's HO/Director columns render identically
     * to the QC workflow's wherever both appear side by side (Report
     * Validation / Director Approvals tabs).
     */
    private function signatureFor(string $approver): string
    {
        return 'Digitally Signed: '.$approver.' [UTC+07:00: '.now('Asia/Jakarta')->format('Y-m-d H:i:s').']';
    }

    /**
     * Director declines — back to pending_ho for MD Production to revise,
     * never back to a QC stage (there isn't one) and never a bare resend
     * (spec §4). revision_count increments so the UI can tell a first send
     * from a resend (SubconOfficialReport::needsRevision()).
     */
    public function applyDirectorDecline(SubconOfficialReport $report, string $reason): void
    {
        if (! $report->isPendingDirector()) {
            throw new \RuntimeException('This report is no longer awaiting Director authorization.');
        }

        DB::transaction(function () use ($report, $reason) {
            $report->status = SubconOfficialReport::STATUS_PENDING_HO;
            $report->revision_count = $report->revision_count + 1;
            $report->director_decline_reason = $reason;
            $report->save();
        });

        $this->logResult($report, 'director_declined', "Director declined: {$reason}");
        $this->logDirectorDecision($report, 'declined', 'Director', "Director rejection recorded for Add Process ({$report->operation}) — back to Report Validation. Reason: {$reason}");
    }

    /**
     * Quiet undo of a send — mirrors SubconAdminController::
     * recallToReportValidation()'s QC-session recall, for this row type:
     * any subcon admin pulling a report back to Report Validation because
     * it was sent by mistake, not a Director decision (no reason, no
     * email, no revision_count bump — unlike applyDirectorDecline()). The
     * HO signature is wiped, not kept, since pending_ho means "not yet
     * approved" under the merged send+HO-approve model (send()'s
     * docblock) — leaving it would make the status/signature pair
     * self-contradictory.
     */
    public function recallToHo(SubconOfficialReport $report, string $actor): void
    {
        if (! $report->isPendingDirector()) {
            throw new \RuntimeException('This report is no longer awaiting Director authorization.');
        }

        DB::transaction(function () use ($report) {
            $report->status = SubconOfficialReport::STATUS_PENDING_HO;
            $report->ho_approval_signature = null;
            $report->ho_approved_at = null;
            $report->save();
        });

        $this->logResult($report, 'recalled', "Recalled back to Report Validation by {$actor}.");
    }

    /**
     * Audit trail via SubconJobLog, spec §3 — job_type "official_report:{operation}".
     * The Add Process PO/vendor (matched_po_number/matched_po_vendor_name) is a
     * *different* PO than the group's main CMT PO (see SubconProductionService::
     * addProcessPurchaseOrdersForGroup()) — display the report's own matched PO,
     * not the CMT order's, or this shows the wrong work order number. order_id
     * still links to the CMT SubconOrder when one exists (the AddPro PO itself
     * has no local SubconOrder row), since that's what the portal page resolves.
     */
    private function logResult(SubconOfficialReport $report, string $status, string $message): void
    {
        // cmtOnly(): without it, a group with both a real CMT order and an
        // Add Process one can attach this log to the wrong order_id — the
        // exact mixup fixed by hand for MPG/PO/2608/00587's own logs (see
        // memory subcon-addpro-po-ingested-as-cmt-order.md).
        $cmtOrder = SubconOrder::cmtOnly()->where('production_group', $report->production_group)->first();

        SubconJobLog::create([
            'order_id' => $cmtOrder?->id,
            'order_number' => $report->matched_po_number ?? $report->production_group,
            'job_type' => "official_report:{$report->operation}",
            'status' => $status,
            'message' => $message,
        ]);
    }

    /**
     * Mirrors logResult() but writes to SubconApprovalLog with gate='director'
     * so an Official Report's Director decision shows up in the Director
     * Approvals tab's "Recent Director decisions" panel alongside the
     * QC/HO/Director workflow's — that panel reads SubconApprovalLog only,
     * and this feature previously logged exclusively to SubconJobLog, so its
     * Director decisions were invisible there even though they'd happened.
     */
    private function logDirectorDecision(SubconOfficialReport $report, string $decision, string $actor, string $note): void
    {
        // cmtOnly(): same reasoning as logResult() above.
        $cmtOrder = SubconOrder::cmtOnly()->where('production_group', $report->production_group)->first();

        SubconApprovalLog::record([
            'order_id' => $cmtOrder?->id,
            'order_number' => $report->matched_po_number ?? $report->production_group,
            'vendor_name' => $report->matched_po_vendor_name ?? $cmtOrder?->vendor?->name,
            'gate' => 'director',
            'decision' => $decision,
            'actor' => $actor,
            'source' => 'portal',
            'note' => $note,
        ]);
    }
}
