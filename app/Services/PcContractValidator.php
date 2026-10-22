<?php

namespace App\Services;

use App\Models\PcContract;
use App\Models\User;

/**
 * Rule IDs below match the PC spec's own numbering (PC-H.., PC-L.., ...)
 * so a user can search the rule text by ID. Rules enforced structurally
 * elsewhere rather than here: PC-W00/W02/W03 (invalid transitions /
 * reject-needs-reason — controller status checks + request validation),
 * PC-X01/S01/S03/S05 (cancel staff-only / vendor sees only their own /
 * vendor-query needs reason / vendor never cancels or edits — controller
 * scoping + route gating).
 *
 * Deferred, not silently dropped: PC-L08/L09 (price-band / last-price
 * checks — Phase 1 deliberately didn't build the price_band table, no
 * historical data to compare against yet) and PC-C01/C02 (computed-total
 * consistency — only relevant for an import/API path that doesn't exist).
 * TODO Phase 3+: price-band comparison once price_band exists.
 */
class PcContractValidator
{
    /**
     * Never blocks — used on every draft save.
     *
     * @return array<int, string>
     */
    public function validateForSave(array $data): array
    {
        $warnings = [];

        if (! empty($data['internal_remarks']) && $this->looksSuspicious($data['internal_remarks'])) {
            $warnings[] = 'PC-H07: Internal Remarks looks like it may contain a phone number, contract number, or control characters.';
        }

        foreach ($data['lines'] ?? [] as $i => $line) {
            $n = $i + 1;

            if (! empty($line['remarks']) && $this->looksSuspicious($line['remarks'])) {
                $warnings[] = "PC-H07: Line {$n} remarks looks like it may contain a phone number, contract number, or control characters.";
            }

            if (! empty($line['plm_ref_id']) && ! empty($data['season_id'])) {
                $plmRef = \App\Models\PcPlmRef::find($line['plm_ref_id']);
                $season = \App\Models\Season::find($data['season_id']);

                if ($plmRef?->season && $season && $plmRef->season !== $season->code) {
                    $warnings[] = "PC-L14: Line {$n}'s PLM season ({$plmRef->season}) differs from the contract season ({$season->code}).";
                }
            }
        }

        return $warnings;
    }

    /**
     * Blocks draft -> pending_vendor_review when non-empty.
     *
     * @return array<int, string>
     */
    public function validateForSubmit(PcContract $contract): array
    {
        $errors = [];

        if (! $contract->contract_date) {
            $errors[] = 'PC-H01: Contract date is required.';
        } elseif ($contract->contract_date->isFuture()) {
            $errors[] = 'PC-H01: Contract date cannot be in the future.';
        }

        $vendor = $contract->vendor;
        if (! $vendor || ! $vendor->is_active || $vendor->type !== 'fabric') {
            $errors[] = 'PC-H02: Vendor must be an active fabric vendor.';
        }

        if ($vendor && $contract->currency && $vendor->currency_code !== $contract->currency->code) {
            $errors[] = "PC-H03: Contract currency no longer matches the vendor's currency — re-open the draft to refresh it.";
        }

        if (! $contract->season || ! $contract->season->is_open) {
            $errors[] = 'PC-H04: Season must be selected and still open for new contracts.';
        }

        foreach ([
            'order_type_id' => 'Order Type',
            'transport_mode_id' => 'Mode of Transport',
            'delivery_term_id' => 'Terms of Delivery',
            'vat_treatment_id' => 'VAT/PPN',
        ] as $field => $label) {
            if (! $contract->$field) {
                $errors[] = "PC-H05: {$label} is required.";
            }
        }

        if (! $contract->payment_term_id) {
            $errors[] = 'PC-H06: No payment term is configured on this vendor — set one on the vendor record first.';
        }

        if (! $contract->fabric_type) {
            $errors[] = 'PC-H11: Fabric Type (WOVEN/KNIT) is required.';
        }

        $deliveries = $contract->deliveries;
        if ($deliveries->isEmpty()) {
            $errors[] = 'PC-H08: At least one delivery schedule row is required.';
        } else {
            foreach ($deliveries as $delivery) {
                if (! $delivery->eta_date) {
                    $errors[] = 'PC-H08: Every delivery row needs a date.';
                } elseif ($contract->contract_date && $delivery->eta_date->lt($contract->contract_date)) {
                    $errors[] = 'PC-H08: A delivery date cannot be before the contract date.';
                }
                if (! $delivery->lot_id) {
                    $errors[] = 'PC-L17: Every delivery row needs a Lot from the master list.';
                }
            }
        }

        $lines = $contract->lines;
        if ($lines->isEmpty()) {
            $errors[] = 'PC-L01: At least one line item is required.';
        }

        $seen = [];
        foreach ($lines as $i => $line) {
            $n = $i + 1;

            if (! $line->fabric_item_id) {
                $errors[] = "PC-L02: Line {$n} must reference a fabric item from the master.";
            }
            if (! $line->plm_ref_id) {
                $errors[] = "PC-L12: Line {$n} must have a PLM ID.";
            }
            if ((float) $line->qty <= 0) {
                $errors[] = "PC-L05: Line {$n} quantity must be greater than zero.";
            }
            if ((float) $line->unit_price <= 0) {
                $errors[] = "PC-L06: Line {$n} price must be greater than zero.";
            }
            if (! $line->hs_code_id) {
                $errors[] = "PC-L16: Line {$n} HS Code is required.";
            }

            $key = $line->fabric_item_id.'|'.$line->plm_ref_id;
            if (isset($seen[$key])) {
                $errors[] = "PC-L10: Line {$n} duplicates an earlier line (same item + PLM).";
            }
            $seen[$key] = true;
        }

        if ($vendor && ! User::where('vendor_id', $vendor->id)->where('role', 'fabric_vendor')->exists()) {
            $errors[] = 'PC-A02: This vendor has no Vendor Portal login yet — create one before submitting.';
        }

        return $errors;
    }

    private function looksSuspicious(string $text): bool
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
            return true;
        }

        // Looks like a phone number (7+ consecutive digits, optionally
        // separated by spaces/dashes) or a contract-number-shaped string.
        if (preg_match('/\d[\d\s\-\/]{6,}\d/', $text)) {
            return true;
        }

        return false;
    }
}
