<?php

namespace App\Services;

use App\Models\PcContract;
use App\Models\PcFabricItem;
use App\Models\PcPlmRef;
use App\Models\Season;
use App\Models\User;

/**
 * Validates Preliminary Contracts according to v4 specification rules.
 * Rule IDs match specification numbering (PC-H.., PC-L.., PC-W..).
 */
class PcContractValidator
{
    public function __construct(private PcPriceBandService $priceBandService) {}

    /**
     * Non-blocking checks for draft saving.
     *
     * @return array<int, string>
     */
    public function validateForSave(array $data): array
    {
        $warnings = [];

        if (! empty($data['internal_remarks']) && $this->looksSuspicious($data['internal_remarks'])) {
            $warnings[] = 'PC-H07: Internal Remarks looks like it may contain a phone number, contract number, or control characters.';
        }

        $season = ! empty($data['season_id']) ? Season::find($data['season_id']) : null;

        foreach ($data['lines'] ?? [] as $i => $line) {
            $n = $i + 1;

            if (! empty($line['remarks']) && $this->looksSuspicious($line['remarks'])) {
                $warnings[] = "PC-H07: Line {$n} remarks looks like it may contain a phone number, contract number, or control characters.";
            }

            if (! empty($line['plm_ref_id']) && $season) {
                $plmRef = PcPlmRef::find($line['plm_ref_id']);
                if ($plmRef?->season && $plmRef->season !== $season->code) {
                    $warnings[] = "PC-L14: Line {$n}'s PLM season ({$plmRef->season}) differs from the contract season ({$season->code}).";
                }
            }
        }

        return $warnings;
    }

    /**
     * Blocking validation checks before submission for approval.
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

        if (! $contract->currency_id && ! $contract->currency) {
            $errors[] = 'PC-H03: Contract currency is required.';
        } elseif ($vendor && $contract->currency && $vendor->currency_code !== $contract->currency->code) {
            $vendor->currency_code = $contract->currency->code;
            $vendor->save();
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

        $currencyCode = $contract->currency?->code ?? $vendor?->currency_code ?? 'USD';
        $vatCode = $contract->vatTreatment?->code ?? 'EXCLUDE';
        $vatRate = (float) ($contract->vat_rate ?? 11.00);

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

            $price = (float) $line->unit_price;
            if ($price <= 0) {
                $errors[] = "PC-L06: Line {$n} price must be greater than zero.";
            } else {
                // PC-L08: Price-band sanity check (ex-VAT)
                $fabricItem = $line->fabricItem ?? PcFabricItem::find($line->fabric_item_id);
                $uom = $line->uom_snapshot ?? $fabricItem?->uom_code ?? 'MTR';
                $exVat = $this->priceBandService->priceExVat($price, $vatCode, $vatRate);
                $bandErr = $this->priceBandService->validatePriceBand($exVat, $currencyCode, $uom, $n);
                if ($bandErr) {
                    $errors[] = $bandErr;
                }
            }

            if (! $line->hs_code_id) {
                $errors[] = "PC-L16: Line {$n} HS Code is required.";
            }

            $key = ($line->fabric_item_id ?? '').'|'.($line->plm_ref_id ?? '');
            if (isset($seen[$key])) {
                $errors[] = "PC-L10: Line {$n} duplicates an earlier line (same item + PLM).";
            }
            $seen[$key] = true;
        }

        return $errors;
    }

    private function looksSuspicious(string $text): bool
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
            return true;
        }

        if (preg_match('/\d[\d\s\-\/]{6,}\d/', $text)) {
            return true;
        }

        return false;
    }
}
