<?php

namespace App\Services;

use App\Models\PcContractLine;

/**
 * Service to enforce PC-L08 (sanity price-band check) and PC-L09 (price-drift check).
 * Baseline price bands are derived from C11_band_harga_awal.csv (computed from
 * historical archives without VAT).
 */
class PcPriceBandService
{
    private const HARD_MIN_FACTOR = 0.2;
    private const HARD_MAX_FACTOR = 5.0;
    private const DRIFT_TOLERANCE = 0.15;
    private const MIN_OBSERVATIONS = 5;

    /**
     * Initial price bands from C11_band_harga_awal.csv:
     * [currency => [uom => [p01, p50, p99, hard_min, hard_max, observations]]]
     */
    private const BANDS = [
        'IDR' => [
            'KG' => ['p01' => 73198.20, 'p50' => 127027.03, 'p99' => 170157.66, 'hard_min' => 14639.640, 'hard_max' => 850788.30, 'observations' => 391],
            'MTR' => ['p01' => 26850.00, 'p50' => 35260.00, 'p99' => 43000.00, 'hard_min' => 5370.000, 'hard_max' => 215000.00, 'observations' => 151],
            'PC' => ['p01' => 2027.03, 'p50' => 4504.50, 'p99' => 7500.00, 'hard_min' => 405.406, 'hard_max' => 37500.00, 'observations' => 101],
            'YD' => ['p01' => 6700.00, 'p50' => 21396.39, 'p99' => 46229.73, 'hard_min' => 1340.000, 'hard_max' => 231148.65, 'observations' => 1180],
        ],
        'RMB' => [
            'KG' => ['p01' => 36.00, 'p50' => 59.00, 'p99' => 76.30, 'hard_min' => 7.200, 'hard_max' => 381.50, 'observations' => 319],
            'MTR' => ['p01' => 0.88, 'p50' => 15.10, 'p99' => 28.70, 'hard_min' => 0.176, 'hard_max' => 143.50, 'observations' => 2390],
            'PC' => ['p01' => 1.00, 'p50' => 2.80, 'p99' => 4.30, 'hard_min' => 0.200, 'hard_max' => 21.50, 'observations' => 63],
        ],
        'USD' => [
            'KG' => ['p01' => 5.55, 'p50' => 8.50, 'p99' => 12.50, 'hard_min' => 1.110, 'hard_max' => 62.50, 'observations' => 96],
            'MTR' => ['p01' => 1.10, 'p50' => 1.60, 'p99' => 3.20, 'hard_min' => 0.220, 'hard_max' => 16.00, 'observations' => 241],
            'PC' => ['p01' => 0.34, 'p50' => 0.37, 'p99' => 0.40, 'hard_min' => 0.068, 'hard_max' => 2.00, 'observations' => 12],
        ],
    ];

    /**
     * Calculate price without VAT (4 decimals, round half-up).
     */
    public function priceExVat(float $price, ?string $vatCode, float $vatRate = 11.00): float
    {
        if (strtoupper((string) $vatCode) === 'INCLUDE') {
            $rate = $vatRate > 0 ? $vatRate : 11.00;
            return round($price / (1 + ($rate / 100)), 4);
        }

        return round($price, 4);
    }

    /**
     * Returns band limits ['hard_min' => float, 'hard_max' => float] or null.
     */
    public function getBand(string $currency, ?string $uom): ?array
    {
        $currency = strtoupper($currency);
        $uom = strtoupper((string) $uom);

        if (isset(self::BANDS[$currency][$uom])) {
            $b = self::BANDS[$currency][$uom];
            if (($b['observations'] ?? 0) >= self::MIN_OBSERVATIONS) {
                return ['hard_min' => $b['hard_min'], 'hard_max' => $b['hard_max']];
            }
        }

        // Fallback: widest limits across all UOMs in this currency
        if (isset(self::BANDS[$currency])) {
            $mins = [];
            $maxs = [];
            foreach (self::BANDS[$currency] as $b) {
                $mins[] = $b['hard_min'];
                $maxs[] = $b['hard_max'];
            }
            if (!empty($mins) && !empty($maxs)) {
                return ['hard_min' => min($mins), 'hard_max' => max($maxs)];
            }
        }

        return null;
    }

    /**
     * PC-L08: Validate unit price against acceptable band for currency + UOM.
     * Returns error string if violated, or null if ok.
     */
    public function validatePriceBand(float $priceExVat, string $currency, ?string $uom, int $lineNo): ?string
    {
        $band = $this->getBand($currency, $uom);
        if (!$band) {
            return null;
        }

        if ($priceExVat < $band['hard_min'] || $priceExVat > $band['hard_max']) {
            $fmtPrice = number_format($priceExVat, 2);
            $fmtMin = number_format($band['hard_min'], 2);
            $fmtMax = number_format($band['hard_max'], 2);
            return "PC-L08: Line {$lineNo} price ({$fmtPrice} {$currency} ex-VAT) is outside acceptable band [{$fmtMin} - {$fmtMax}]. Check for currency or unit errors.";
        }

        return null;
    }

    /**
     * PC-L09: Warning if price drifts > 15% from last confirmed contract price.
     */
    public function checkPriceDrift(?string $fabricItemId, float $priceExVat, string $currency, ?string $uom, int $lineNo): ?string
    {
        if (!$fabricItemId) {
            return null;
        }

        // Look up latest confirmed contract line for this item
        $lastLine = PcContractLine::where('fabric_item_id', $fabricItemId)
            ->whereHas('contract', function ($q) use ($currency) {
                $q->whereIn('status', ['confirmed', 'approved', 'sent_to_supplier'])
                    ->whereHas('currency', fn ($cq) => $cq->where('code', $currency));
            })
            ->latest('created_at')
            ->first();

        if (!$lastLine || !$lastLine->unit_price_ex_vat) {
            return null;
        }

        $lastPrice = (float) $lastLine->unit_price_ex_vat;
        if ($lastPrice <= 0) {
            return null;
        }

        $ratio = abs($priceExVat - $lastPrice) / $lastPrice;
        if ($ratio > self::DRIFT_TOLERANCE) {
            $pct = round($ratio * 100);
            $direction = $priceExVat > $lastPrice ? 'higher' : 'lower';
            $fmtPrice = number_format($priceExVat, 2);
            $fmtLast = number_format($lastPrice, 2);
            return "PC-L09: Line {$lineNo} price ({$fmtPrice} {$currency}) is {$pct}% {$direction} than last price ({$fmtLast} {$currency}). A reason is required.";
        }

        return null;
    }
}
