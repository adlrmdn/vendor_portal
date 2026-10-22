<?php

namespace App\Services;

use App\Models\NoteTemplate;
use App\Models\PcContract;
use App\Models\ToleranceTemplate;

/**
 * Copies master data onto the contract ONCE, at the confirm transition,
 * so a later edit to NoteTemplate/ToleranceTemplate/vendor/etc. never
 * retroactively changes an already-confirmed contract. No existing
 * feature in this app does a clean one-time freeze like this
 * (SubconConsumptionService::persist() is an upsert-and-recompute, not a
 * freeze) — this is the first instance of the pattern.
 *
 * Only ever called from inside PcContractApprovalService::approve()'s
 * transaction — never call this standalone.
 */
class PcContractFreezeService
{
    public function __construct(private PcContractNumberService $numberService) {}

    public function freeze(PcContract $contract): void
    {
        // A revision reuses its parent's number — only issue a new one the
        // first time a contract family reaches confirmed.
        if (! $contract->contract_number) {
            $contract->contract_number = $this->numberService->issue();
        }

        // contract_date is the preparer's own input at draft time (PC-H01:
        // required, defaults to today, never in the future) — NOT
        // reassigned here. Freezing only touches header_snapshot/notes/
        // tolerances; the date the deal was struck stays whatever the
        // preparer picked when they drafted it.
        $contract->header_snapshot = $this->buildHeaderSnapshot($contract);
        $contract->save();

        $this->freezeNotes($contract);
        $this->freezeTolerances($contract);
    }

    private function buildHeaderSnapshot(PcContract $contract): array
    {
        $vendor = $contract->vendor;

        return [
            'vendor_name' => $vendor?->name,
            'vendor_code' => $vendor?->vendor_code,
            'vendor_country' => $vendor?->country,
            'vendor_contact_person' => $vendor?->contact_person,
            'vendor_contact_info' => $vendor?->contact_info,
            'currency_code' => $contract->currency?->code,
            'currency_label' => $contract->currency?->label,
            'payment_term_label' => $contract->paymentTerm?->label,
            'payment_term_days' => $contract->paymentTerm?->days,
            'delivery_term_label' => $contract->deliveryTerm?->label,
            'transport_mode_label' => $contract->transportMode?->label,
            'vat_treatment_code' => $contract->vatTreatment?->code,
            'vat_rate' => $contract->vat_rate,
            'season_label' => $contract->season?->label,
            'order_type_label' => $contract->orderType?->label,
            'fabric_type' => $contract->fabric_type,
        ];
    }

    private function freezeNotes(PcContract $contract): void
    {
        $contract->notes()->delete(); // a revision re-freezing clears any stale copy first

        foreach (NoteTemplate::active()->effectiveOn($contract->contract_date)->orderBy('clause_number')->get() as $template) {
            $contract->notes()->create([
                'clause_number' => $template->clause_number,
                'body' => $template->body,
                'emphasis' => $template->emphasis,
                'source_note_template_id' => $template->id,
            ]);
        }
    }

    private function freezeTolerances(PcContract $contract): void
    {
        $contract->tolerances()->delete();

        if (! $contract->fabric_type) {
            return;
        }

        $templates = ToleranceTemplate::active()
            ->forFabricType($contract->fabric_type)
            ->effectiveOn($contract->contract_date)
            ->get();

        foreach ($templates as $template) {
            $contract->tolerances()->create([
                'fabric_type' => $template->fabric_type,
                'row_label' => $template->row_label,
                'tolerance' => $template->tolerance,
                'highlight' => $template->highlight,
                'sort_order' => $template->sort_order,
                'source_tolerance_template_id' => $template->id,
            ]);
        }
    }
}
