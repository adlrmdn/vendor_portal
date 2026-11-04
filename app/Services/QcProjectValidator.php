<?php

namespace App\Services;

use App\Models\SubconOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cross-checks a QC project's stored snapshot against the synced sources at report
 * generation time (GET /qc/print — what "Verify → Send" calls), so a stale PO/vendor never
 * goes out in the verification email.
 *
 * packaging_projects.po_info / po_vendor are captured once when the console downloads a
 * project. D365 can later reissue the CMT PO, and a group's Add Process (AddPro) PO can be
 * stored in place of its CMT PO — the console then shows the wrong vendor indefinitely.
 * Sources, most authoritative first: the synced SubconOrder (portal, from D365), then VSM's
 * production_groups → po_headers (same mapping the console itself copies from).
 *
 * Auto-corrected (written back to packaging_projects, then rendered):
 *   - po_info   when the stored PO is not one of the group's POs
 *   - po_vendor when it differs from the vendor name of the (corrected) PO
 * Reported only, never written:
 *   - sizes in the production group (VSM) that are missing from the session's report rows.
 *     The report rows belong to the console (inspection data, D365 job-transaction keys), so
 *     inventing rows here is unsafe.
 *
 * Completed/removed projects are historical records and are never touched. Never throws —
 * a failed check must not block the report.
 */
class QcProjectValidator
{
    private const CLOSED_STATUSES = ['completed', 'removed', 'removed_completed'];

    /**
     * @return array{healed: array<string, array{0: ?string, 1: ?string}>, warnings: list<string>}
     *                                                                                             healed: field => [from, to]
     */
    public function validateAndHeal(string $projectId, ?string $sessionId = null): array
    {
        $result = ['healed' => [], 'warnings' => []];

        try {
            $qms = DB::connection('qms');
            // Named columns only: the row also carries verified_doc, a multi-MB base64 PDF.
            $project = $qms->table('packaging_projects')->where('project_id', $projectId)
                ->first(['project_id', 'production_group', 'po_info', 'po_vendor', 'status']);
            if (! $project || in_array((string) $project->status, self::CLOSED_STATUSES, true)) {
                return $result;
            }

            $group = trim((string) ($project->production_group ?? ''));
            if ($group === '') {
                return $result;
            }

            $updates = [];

            $storedPo = (string) ($project->po_info ?? '');
            $poFix = $this->correctPo($storedPo, $group, $result['warnings']);
            if ($poFix !== null) {
                $updates['po_info'] = $poFix;
                $result['healed']['po_info'] = [$storedPo, $poFix];
            }

            $vendorFix = $this->correctVendor($poFix ?? $storedPo, (string) ($project->po_vendor ?? ''), $group);
            if ($vendorFix !== null) {
                $updates['po_vendor'] = $vendorFix;
                $result['healed']['po_vendor'] = [(string) ($project->po_vendor ?? ''), $vendorFix];
            }

            if ($sessionId !== null && $sessionId !== '') {
                $this->checkSizes($projectId, $sessionId, $group, $result['warnings']);
            }

            $this->healCuttingBaseline($projectId, $group, $result['healed'], $result['warnings']);

            if ($updates) {
                $updates['updated_at'] = now();
                $qms->table('packaging_projects')->where('project_id', $projectId)->update($updates);
                Log::warning('QC project validation: stale snapshot corrected before report generation', [
                    'project' => $projectId, 'production_group' => $group, 'healed' => $result['healed'],
                ]);
            }
            foreach ($result['warnings'] as $warning) {
                Log::warning('QC project validation: '.$warning, ['project' => $projectId, 'production_group' => $group]);
            }
        } catch (\Throwable $e) {
            Log::warning('QC project validation failed, generating the report without it', [
                'project' => $projectId, 'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /** The corrected PO number, or null when the stored one is fine (or cannot be judged). */
    private function correctPo(string $storedPo, string $group, array &$warnings): ?string
    {
        // A multi-CMT project stores its POs comma-joined; one match means it is still valid.
        $stored = array_values(array_filter(array_map('trim', explode(',', $storedPo))));

        // cmtOnly(): an Add Process PO sharing the group is never a real
        // candidate — including it here would turn an otherwise-unambiguous
        // single-CMT-order group into a false "ambiguous" warning (count()
        // === 2 instead of 1) and block the healing this method exists to do.
        $orders = SubconOrder::cmtOnly()->where('production_group', $group)->pluck('order_number')->all();
        if ($orders) {
            if (array_intersect($stored, $orders)) {
                return null;
            }
            if (count($orders) === 1) {
                return $orders[0];
            }
            $warnings[] = "PO '{$storedPo}' is not one of this group's POs (".implode(', ', $orders).') and the right one is ambiguous';

            return null;
        }

        // No synced order (yet): fall back to the mapping the console itself copies from.
        $vsm = $this->vsmGroupPo($group);
        if ($vsm && strtoupper((string) $vsm->PurchPoolId) === 'CMT' && ! in_array($vsm->PurchaseOrderNumber, $stored, true)) {
            return (string) $vsm->PurchaseOrderNumber;
        }

        return null;
    }

    /** The corrected vendor name, or null when the stored one is fine (or cannot be judged). */
    private function correctVendor(string $po, string $storedVendor, string $group): ?string
    {
        $pos = array_values(array_filter(array_map('trim', explode(',', $po))));
        if (count($pos) !== 1) {
            return null; // multi-PO projects keep their per-PO vendor list
        }

        $expected = null;
        try {
            $expected = DB::connection('vsm')->table('po_headers')
                ->where('PurchaseOrderNumber', $pos[0])->value('PurchaseOrderName');
        } catch (\Throwable $e) {
            // VSM unreachable — fall through to the portal's own vendor record.
        }
        if (! $expected) {
            $order = SubconOrder::with('vendor')->cmtOnly()->where('production_group', $group)->where('order_number', $pos[0])->first();
            $expected = $order?->vendor?->name;
        }

        $expected = trim((string) $expected);

        return ($expected !== '' && $expected !== $storedVendor) ? $expected : null;
    }

    private function checkSizes(string $projectId, string $sessionId, string $group, array &$warnings): void
    {
        try {
            $reportSizes = DB::connection('qms')->table('packaging_project_reports')
                ->where('project_id', $projectId)->where('session_id', $sessionId)->pluck('size_val')->map(fn ($s) => (string) $s)->all();
            if (! $reportSizes) {
                return; // nothing loaded for this session yet — not a drift
            }
            $groupSizes = DB::connection('vsm')->table('production_group_lines')
                ->where('ProductionGroup', $group)->pluck('Size')->map(fn ($s) => (string) $s)->unique()->all();
            $missing = array_values(array_diff($groupSizes, $reportSizes));
            if ($missing) {
                $warnings[] = 'sizes in the production group but not in this report: '.implode(', ', $missing);
            }
        } catch (\Throwable $e) {
            // VSM unreachable — size check skipped.
        }
    }

    private function vsmGroupPo(string $group): ?object
    {
        try {
            return DB::connection('vsm')->table('production_groups as pg')
                ->join('po_headers as ph', 'ph.PurchaseOrderNumber', '=', 'pg.PONumber')
                ->where('pg.ProductionGroup', $group)
                ->first(['ph.PurchaseOrderNumber', 'ph.PurchPoolId']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Backfills a merged project's missing Cut Qty baseline row(s)
     * (`packaging_project_reports`, `session_id IS NULL`) straight from
     * D365. QcReportPdfService::context()'s $baseGoodQtyBySize and
     * QcApprovalController::resolveQcCutQtyBySize() already correctly SUM
     * every baseline row per size — that aggregation was never the bug. The
     * data underneath it was incomplete: the QC Console's own "Sync D365"
     * can silently fail to refresh ONE linked group (no retry, no visible
     * error on a transient D365 timeout) and leave that group's cut job's
     * baseline row missing forever, permanently understating Cut Qty for
     * every size the groups share. Confirmed live on MPG/PO/2608/00649: the
     * console showed the correct combined 10,215 right after a successful
     * Sync D365, but the portal's own report kept showing 6,215 (one
     * group's worth) because the OTHER group's refresh never wrote
     * anything that time — and the next Sync D365 attempt could fail the
     * same way again. This heals it from the portal's own D365 access
     * every time the project is touched (print, Verify/Send, HO/Director
     * approval), instead of depending on the console's refresh succeeding.
     *
     * Only ever INSERTs — never touches a job that already has a baseline
     * row, so it can't clobber anything the console or an inspector has
     * written. Best-effort: any failure is reported as a warning and never
     * blocks the report.
     */
    private function healCuttingBaseline(string $projectId, string $primaryGroup, array &$healed, array &$warnings): void
    {
        try {
            $qms = DB::connection('qms');

            $groups = [$primaryGroup];
            try {
                $groups = array_merge($groups, $qms->table('packaging_project_group_links')
                    ->where('primary_production_group', $primaryGroup)->pluck('production_group')->all());
            } catch (\Throwable $e) {
                // link table missing/unreachable — heal just the primary group.
            }

            $token = $this->d365Token();
            if (! $token) {
                return;
            }

            $backfilled = [];
            foreach (array_unique($groups) as $group) {
                $cutJobId = $this->resolveCutJobId($group, $token);
                if (! $cutJobId) {
                    continue;
                }

                $alreadyPresent = $qms->table('packaging_project_reports')
                    ->where('project_id', $projectId)->whereNull('session_id')
                    ->where('job_transaction_id', $cutJobId)->exists();
                if ($alreadyPresent) {
                    continue;
                }

                $lines = $this->fetchJobLines($cutJobId, $token);
                $rows = $this->buildBaselineRows($projectId, $cutJobId, $lines);
                if (empty($rows)) {
                    continue;
                }

                $qms->table('packaging_project_reports')->insertOrIgnore($rows);
                $backfilled[] = "{$group} ({$cutJobId}, ".count($rows).' sizes)';
            }

            if ($backfilled) {
                $healed['cutting_baseline'] = [null, implode('; ', $backfilled)];
                Log::warning('QC project validation: backfilled missing Cut Qty baseline row(s)', [
                    'project' => $projectId, 'backfilled' => $backfilled,
                ]);
            }
        } catch (\Throwable $e) {
            $warnings[] = 'Cut Qty baseline backfill failed: '.$e->getMessage();
        }
    }

    private function d365Token(): ?string
    {
        $cacheKey = 'd365_access_token';
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $tenantId = config('services.d365.tenant_id');
        $resource = config('services.d365.resource');
        $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.d365.client_id'),
            'client_secret' => config('services.d365.client_secret'),
            'scope' => "{$resource}/.default",
        ]);
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        Cache::put($cacheKey, $data['access_token'], $data['expires_in'] - 300);

        return $data['access_token'];
    }

    private function resolveCutJobId(string $productionGroup, string $token): ?string
    {
        $resource = config('services.d365.resource');
        $url = $resource.'/data/JobTransactionHeaders?$filter='.rawurlencode("ProductionGroup eq '{$productionGroup}'").'&cross-company=true';
        $resp = Http::withToken($token)->acceptJson()->timeout(30)->get($url);
        if (! $resp->successful()) {
            return null;
        }
        foreach ($resp->json('value') ?? [] as $h) {
            if (strcasecmp((string) ($h['Operation'] ?? ''), 'CMT-Cut') === 0) {
                return $h['JobTransactionId'] ?? null;
            }
        }

        return null;
    }

    private function fetchJobLines(string $jobId, string $token): array
    {
        $resource = config('services.d365.resource');
        $url = $resource.'/data/JobTransactionLinesDetails?$filter='.rawurlencode("JobTransactionId eq '{$jobId}'").'&cross-company=true';
        $resp = Http::withToken($token)->acceptJson()->timeout(30)->get($url);

        return $resp->successful() ? ($resp->json('value') ?? []) : [];
    }

    /**
     * Same shape as forge's own fetch_and_store_lines() insert (db.rs) —
     * summed per size across every raw D365 line sharing that size, reject
     * categories summed the same way, other metadata taken from the last
     * line seen. session_id left NULL: this IS the baseline row.
     */
    private function buildBaselineRows(string $projectId, string $jobId, array $lines): array
    {
        $bySize = [];
        foreach ($lines as $rec) {
            $size = trim((string) ($rec['Size'] ?? ''));
            if ($size === '') {
                continue;
            }
            $bySize[$size] ??= ['good' => 0, 'reject' => 0, 'produksi' => 0, 'finishing' => 0, 'embro' => 0,
                'cutting' => 0, 'printing' => 0, 'sewing' => 0, 'washing' => 0, 'bahan' => 0, 'sample' => 0, 'hilang' => 0];
            $bySize[$size]['good'] += (int) ($rec['TotalGoodQty'] ?? 0);
            $bySize[$size]['reject'] += (int) ($rec['TotalRejectQty'] ?? 0);
            $bySize[$size]['produksi'] += (int) ($rec['RejectProduksi'] ?? 0);
            $bySize[$size]['finishing'] += (int) ($rec['RejectFinishing'] ?? 0);
            $bySize[$size]['embro'] += (int) ($rec['RejectEmbro'] ?? 0);
            $bySize[$size]['cutting'] += (int) ($rec['RejectCutting'] ?? 0);
            $bySize[$size]['printing'] += (int) ($rec['RejectPrinting'] ?? 0);
            $bySize[$size]['sewing'] += (int) ($rec['RejectSewing'] ?? 0);
            $bySize[$size]['washing'] += (int) ($rec['RejectWashing'] ?? 0);
            $bySize[$size]['bahan'] += (int) ($rec['RejectBahan'] ?? 0);
            $bySize[$size]['sample'] += (int) ($rec['TotalQtySample'] ?? 0);
            $bySize[$size]['hilang'] += (int) ($rec['BarangHilang'] ?? 0);
            $bySize[$size]['last'] = $rec;
        }

        $rows = [];
        foreach ($bySize as $size => $agg) {
            $rec = $agg['last'] ?? [];
            $rows[] = [
                'report_id' => "{$projectId}_{$jobId}_{$size}",
                'session_id' => null,
                'project_id' => $projectId,
                'data_area_id' => $rec['dataAreaId'] ?? null,
                'line_no' => isset($rec['No']) ? (int) $rec['No'] : null,
                'job_transaction_id' => $jobId,
                'item_id' => $rec['ItemId'] ?? null,
                'size_val' => $size,
                'global_display_order' => isset($rec['GlobalDisplayOrder']) ? (int) $rec['GlobalDisplayOrder'] : null,
                'reject_produksi' => $agg['produksi'],
                'reject_finishing' => $agg['finishing'],
                'reject_embro' => $agg['embro'],
                'qty_order' => isset($rec['QtyOrder']) ? (int) $rec['QtyOrder'] : null,
                'total_qty_sample' => $agg['sample'],
                'barang_hilang' => $agg['hilang'],
                'reject_cutting' => $agg['cutting'],
                'total_reject_qty' => $agg['reject'],
                'reject_printing' => $agg['printing'],
                'ref_rec_id' => isset($rec['RefRecId']) ? (int) $rec['RefRecId'] : null,
                'total_good_qty' => $agg['good'],
                'reject_sewing' => $agg['sewing'],
                'reject_washing' => $agg['washing'],
                'gramasi' => isset($rec['Gramasi']) ? (float) $rec['Gramasi'] : null,
                'btj' => isset($rec['BTJ']) ? (int) $rec['BTJ'] : null,
                'reject_bahan' => $agg['bahan'],
                'session_qty' => 0.0,
                'session_version' => 'v1.0',
                'created_at' => now(),
            ];
        }

        return $rows;
    }
}
