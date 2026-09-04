<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Shared read path for rpa_queues invoice/deduction jobs, decorated with the
 * same derived fields (style, portal, report availability, reason) the
 * finance admin tables show. Used by both the finance admin tabs (all
 * vendors, read/write — see FinanceAdminController) and the subcon vendor's
 * read-only Invoices tab (scoped to their own vendor name — see
 * SubconVendorController).
 *
 * Deliberately not date-scoped: a completed job stays listed as long as it's
 * unchecked, however old it is, rather than dropping off the review list at
 * midnight while still unreviewed.
 */
class RpaQueueReadService
{
    public const STATUSES = ['waiting', 'processing', 'completed', 'failed'];

    /**
     * @return Collection<int,object>
     */
    public function rows(string $rpaType, ?string $vendorName = null): Collection
    {
        $rows = DB::connection('rpa')->table('rpa_queues')
            ->where('rpa_type', $rpaType)
            ->whereIn('status', self::STATUSES)
            ->orderByDesc('updated_at')
            ->get();

        $rows = $rows->map(function ($row) {
            $payload = json_decode($row->payload ?? '', true) ?: [];
            $row->po = $payload['PO'] ?? '';
            $row->vendor_name = $payload['vendor_name'] ?? '';
            $row->amount = $payload['amount'] ?? null;
            $row->reason = $row->status === 'failed' ? trim((string) $row->error_message) : '';
            $row->has_report = ! empty($payload['signed_doc']) || ! empty($payload['s3_key']);
            $row->checked = (bool) $row->checked;
            // Only ever set for a completed deduction row — the debit_note
            // RPA bot's finalize call writes these once it knows the real
            // D365 journal number (RpaQueueService::regenerateDeductionDocs).
            $row->document_no = $payload['document_no'] ?? null;

            return $row;
        });

        if ($vendorName !== null) {
            $needle = strtolower($vendorName);
            $rows = $rows->filter(fn ($row) => strtolower($row->vendor_name) === $needle)->values();
        }

        // Style name (garment article), keyed by the project (entity_id) these
        // jobs were queued against — one batched lookup rather than N+1.
        $entityIds = $rows->pluck('entity_id')->filter()->unique()->values()->all();
        $styleByEntity = [];
        if (! empty($entityIds)) {
            try {
                $styleByEntity = DB::connection('qms')->table('packaging_projects')
                    ->whereIn('project_id', $entityIds)
                    ->pluck('article_name', 'project_id')->all();
            } catch (\Throwable $e) {
                // Style unavailable — rows just show '-' for it.
            }
        }

        $portals = $this->resolvePortals($rows->pluck('vendor_name')->all());

        return $rows->map(function ($row) use ($styleByEntity, $portals) {
            $row->style = $styleByEntity[$row->entity_id] ?? null;
            $row->portal = $portals[strtolower($row->vendor_name)] ?? 'Fabric';

            return $row;
        });
    }

    /**
     * The signed PDF, always returned as a `data:application/pdf;base64,...`
     * URI (what FinanceAdminController::report() / SubconAdminController::
     * invoiceReport() expect), resolving it from S3 when
     * RpaQueueService::offloadSignedDocToS3() moved it out of the row. The S3
     * object itself is raw PDF bytes (the RPA bot's contract — see that
     * method's docblock), so it's re-wrapped into a data URI here rather than
     * returned as-is. Field name is plain `s3_key` (not `signed_doc_s3_key`).
     * Returns '' if none was attached.
     *
     * @param  array<string,mixed>  $payload
     */
    public function resolveSignedDoc(array $payload): string
    {
        $signedDoc = (string) ($payload['signed_doc'] ?? '');
        if ($signedDoc !== '' || empty($payload['s3_key'])) {
            return $signedDoc;
        }

        try {
            $bytes = (string) Storage::disk('rpa_lake')
                ->get(preg_replace('#^automaton/#', '', (string) $payload['s3_key']));

            return 'data:application/pdf;base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The plain report (no debit-note page), for deduction rows only —
     * SubconAdminController::debitNoteReport always shows this instead of
     * resolveSignedDoc()'s debit-note-combined document, which Finance's tab
     * shows. Stored under `original_report`/`original_report_s3_key`
     * (RpaQueueService::offloadOriginalReportToS3); absent on rows queued
     * before that field existed, or on invoice rows (which never had a
     * separate document to begin with — resolveSignedDoc() already is the
     * plain report for those). Returns '' if unavailable.
     *
     * @param  array<string,mixed>  $payload
     */
    public function resolveOriginalReport(array $payload): string
    {
        $doc = (string) ($payload['original_report'] ?? '');
        if ($doc !== '' || empty($payload['original_report_s3_key'])) {
            return $doc;
        }

        try {
            $bytes = (string) Storage::disk('rpa_lake')
                ->get(preg_replace('#^automaton/#', '', (string) $payload['original_report_s3_key']));

            return 'data:application/pdf;base64,'.base64_encode($bytes);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Vendor.type (fabric|subcon) is the authoritative domain classification
     * (CLAUDE.md), and the payload already carries the vendor name — so this
     * matches directly on Vendor rather than trying to join the rpa_queues
     * entity_id back through qms.packaging_projects.production_group ->
     * subcon_orders.production_group. That production_group can drift on the
     * D365 side after the packaging project snapshot was taken (the same class
     * of staleness D365JobTransactionService already self-heals for
     * cmt_pak_job_id), so a strict equality join there silently mislabels
     * drifted or not-yet-locally-synced subcon orders as fabric.
     *
     * @param  list<string>  $vendorNames
     * @return array<string,string> lowercased vendor name => 'Fabric'|'Subcon'
     */
    private function resolvePortals(array $vendorNames): array
    {
        $names = collect($vendorNames)->filter()->unique();
        if ($names->isEmpty()) {
            return [];
        }

        return Vendor::whereIn(DB::raw('LOWER(name)'), $names->map(fn ($n) => strtolower($n)))
            ->get(['name', 'type'])
            ->mapWithKeys(fn ($v) => [strtolower($v->name) => $v->type === 'subcon' ? 'Subcon' : 'Fabric'])
            ->all();
    }
}
