<?php

namespace App\Services;

use App\Models\SubconOrder;
use Illuminate\Support\Facades\DB;
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

        $orders = SubconOrder::where('production_group', $group)->pluck('order_number')->all();
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
            $order = SubconOrder::with('vendor')->where('production_group', $group)->where('order_number', $pos[0])->first();
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
}
