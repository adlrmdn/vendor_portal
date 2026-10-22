<?php

namespace App\Services;

use App\Models\PcNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Race-safe document-number issuance. Lock shape copied verbatim from
 * SubconVendorController::submitCuttingReport's row-lock pattern — nothing
 * else in this app does real gapless sequential numbering (D365-supplied
 * numbers are used as-is elsewhere; PackingSlip.slip_number is
 * uniqid-based, not sequential).
 */
class PcContractNumberService
{
    private const ROMAN_MONTHS = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function issue(string $docType = 'contract'): string
    {
        return DB::transaction(function () use ($docType) {
            $yy = now()->format('y');

            $seq = PcNumberSequence::where('doc_type', $docType)
                ->where('year_yy', $yy)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                // First-ever call for this (doc_type, year_yy): two concurrent
                // requests could both reach here before either commits. The
                // unique index on (doc_type, year_yy) makes the loser's
                // insert fail instead of duplicate — catch that and just
                // re-select (with the lock) the winner's row.
                try {
                    $seq = PcNumberSequence::create(['doc_type' => $docType, 'year_yy' => $yy, 'last_number' => 0]);
                } catch (\Throwable $e) {
                    // fall through to the re-select below
                }
                $seq = PcNumberSequence::where('doc_type', $docType)->where('year_yy', $yy)->lockForUpdate()->first();
            }

            $seq->last_number += 1;
            $seq->save();

            $roman = self::ROMAN_MONTHS[(int) now()->format('n')];

            return sprintf('MPG/PC/PDV/%s/%s/%03d', $yy, $roman, $seq->last_number);
        });
    }
}
