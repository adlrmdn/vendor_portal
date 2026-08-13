<?php

namespace App\Services;

use App\Models\PoItem;

/**
 * Parses vendor roll lists (Excel/CSV/PDF) into rows for the process-item page
 * and builds the matching downloadable template. Quantities are always in the
 * item's original order metric; the page converts YD⇄M on its own.
 */
class RollsImportService
{
    /**
     * Header row of the uniform import template. Mirrors the form: every
     * metric column is present, only the order metric is mandatory. The Excel
     * importer maps columns by these header names (position-independent).
     */
    public static function templateHeader(PoItem $item): array
    {
        $unit = strtoupper($item->unit);
        $base = ['Roll No.', 'Bale No.', 'Lot ID', 'Color'];

        if (in_array($unit, ['YD', 'M', 'KG'])) {
            return array_merge($base, ['Length (YD)', 'Length (M)', 'Weight (KG)']);
        }

        // Non-metric order units (PCS/UNIT): qty column plus optional lengths.
        return array_merge($base, ['Qty ('.$unit.')', 'Length (YD)', 'Length (M)']);
    }

    /**
     * Map spreadsheet rows to roll data. If a header row matching the template
     * is found, columns are mapped by name; otherwise the legacy positional
     * format ([lot id, quantity]) is assumed.
     *
     * @return array<int, array{vendor_roll_no: string, bale_no: string, internal_id: string, color: string, quantity: float}>
     */
    public static function parseRows(array $rows): array
    {
        $map = null;
        $data = [];

        foreach ($rows as $row) {
            $row = array_map(fn ($c) => is_string($c) ? trim($c) : $c, array_values((array) $row));

            if ($map === null && ($detected = self::detectHeader($row)) !== null) {
                $map = $detected;

                continue;
            }

            if ($map !== null) {
                $metric = function (string $key) use ($map, $row): ?float {
                    $n = isset($map[$key]) ? self::toNumber($row[$map[$key]] ?? null) : null;

                    return ($n !== null && $n > 0) ? $n : null;
                };
                $metrics = [
                    'quantity' => $metric('quantity'),
                    'length_yd' => $metric('length_yd'),
                    'length_m' => $metric('length_m'),
                    'weight' => $metric('weight'),
                ];
                // A row needs at least one usable figure to count as a roll.
                if (array_filter($metrics) === []) {
                    continue;
                }
                $cell = fn (string $key) => isset($map[$key]) ? (string) ($row[$map[$key]] ?? '') : '';
                $data[] = array_filter($metrics, fn ($v) => $v !== null) + [
                    'vendor_roll_no' => $cell('vendor_roll_no'),
                    'bale_no' => $cell('bale_no'),
                    'internal_id' => $cell('internal_id'),
                    'color' => $cell('color'),
                ];
            } else {
                // Legacy positional format: first column lot id, second quantity.
                $qty = self::toNumber($row[1] ?? null);
                if ($qty === null || $qty <= 0) {
                    continue;
                }
                $data[] = [
                    'vendor_roll_no' => '',
                    'bale_no' => '',
                    'internal_id' => (string) ($row[0] ?? ''),
                    'color' => '',
                    'quantity' => $qty,
                ];
            }
        }

        return $data;
    }

    /**
     * Heuristic PDF packing-list parser. Works line by line:
     * - skips summary/footer lines (total, page, ...)
     * - quantity = the last decimal-bearing number on the line (fabric lengths
     *   are decimals), falling back to the last number
     * - id-ish tokens (containing digits/dashes) become roll no + lot id
     * - supports thousands separators and comma decimals
     */
    public static function parsePdfText(string $text): array
    {
        $data = [];

        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/\b(total|subtotal|balance|page|carried|brought|amount|signature)\b/i', $line)) {
                continue;
            }

            $numbers = [];
            $ids = [];
            foreach (preg_split('/\s+/', $line) as $i => $tok) {
                $n = self::toNumber($tok);
                if ($n !== null) {
                    $numbers[] = ['i' => $i, 'raw' => $tok, 'value' => $n, 'decimal' => (bool) preg_match('/[.,]\d+$/', $tok)];
                } elseif (preg_match('#^[A-Za-z0-9][A-Za-z0-9/_.-]*$#', $tok) && preg_match('#[\d/_-]#', $tok)) {
                    // Id-ish: alphanumeric containing digits or separators. Plain
                    // words (descriptions, colors) don't qualify.
                    $ids[] = $tok;
                }
            }
            if (empty($numbers)) {
                continue;
            }

            // All metrics when available: two decimal numbers whose ratio is the
            // yard/metre factor (0.9144) are a YD + M pair; a further decimal on
            // the line is taken as the weight (KG).
            $lengthYd = $lengthM = $weight = null;
            $decimals = array_values(array_filter($numbers, fn ($n) => $n['decimal'] && $n['value'] >= 0.01 && $n['value'] <= 1000000));
            $pair = self::findYardMetrePair($decimals);
            $qtyTok = null;
            if ($pair !== null) {
                [$ydTok, $mTok] = $pair;
                $lengthYd = $ydTok['value'];
                $lengthM = $mTok['value'];
                foreach ($decimals as $n) {
                    if ($n['i'] !== $ydTok['i'] && $n['i'] !== $mTok['i']) {
                        $weight = $n['value'];
                        break;
                    }
                }
            } else {
                foreach (array_reverse($numbers) as $n) {
                    if ($n['decimal']) {
                        $qtyTok = $n;
                        break;
                    }
                }
                $qtyTok = $qtyTok ?? end($numbers);
                if ($qtyTok['value'] < 0.01 || $qtyTok['value'] > 1000000) {
                    continue;
                }
            }

            $usedIndexes = $qtyTok !== null
                ? [$qtyTok['i']]
                : array_map(fn ($n) => $n['i'], array_filter([$pair[0] ?? null, $pair[1] ?? null]));

            // One id → lot id (legacy behaviour); two → roll no then lot id.
            $rollNo = count($ids) >= 2 ? $ids[0] : '';
            $lot = count($ids) >= 2 ? $ids[1] : ($ids[0] ?? '');
            if ($lot === '') {
                // No textual id: a plain integer before the quantity often is one.
                foreach ($numbers as $n) {
                    if (! in_array($n['i'], $usedIndexes) && ! $n['decimal']) {
                        $lot = $n['raw'];
                        break;
                    }
                }
            }

            $row = [
                'vendor_roll_no' => $rollNo,
                'bale_no' => '',
                'internal_id' => $lot,
                'color' => '',
            ];
            if ($qtyTok !== null) {
                $row['quantity'] = $qtyTok['value'];
            } else {
                $row['length_yd'] = $lengthYd;
                $row['length_m'] = $lengthM;
                if ($weight !== null) {
                    $row['weight'] = $weight;
                }
            }
            $data[] = $row;
        }

        return $data;
    }

    /**
     * Find two decimal numbers whose ratio is the yard→metre factor (0.9144,
     * ±1%). Returns [ydToken, mToken] or null.
     */
    private static function findYardMetrePair(array $decimals): ?array
    {
        $count = count($decimals);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $decimals[$i];
                $b = $decimals[$j];
                if ($a['value'] <= 0 || $b['value'] <= 0) {
                    continue;
                }
                if (abs($b['value'] / $a['value'] - 0.9144) < 0.01) {
                    return [$a, $b]; // a = YD, b = M
                }
                if (abs($a['value'] / $b['value'] - 0.9144) < 0.01) {
                    return [$b, $a];
                }
            }
        }

        return null;
    }

    /**
     * Detect the template header row: needs a quantity-ish column plus at
     * least one other known column. Returns [field => column index].
     */
    private static function detectHeader(array $row): ?array
    {
        $map = [];
        foreach ($row as $i => $cell) {
            if (! is_string($cell) || $cell === '') {
                continue;
            }
            $h = strtolower($cell);
            if (str_contains($h, 'bale')) {
                $map['bale_no'] = $i;
            } elseif (str_contains($h, 'lot') || str_contains($h, 'batch')) {
                $map['internal_id'] = $i;
            } elseif (str_contains($h, 'colo')) {
                $map['color'] = $i;
            } elseif (preg_match('/\byd\b|yard|\(yd\)/', $h)) {
                $map['length_yd'] = $i;
            } elseif (preg_match('/\bkg\b|\(kg\)|weight/', $h)) {
                $map['weight'] = $i;
            } elseif (preg_match('/\(m\)|meter|metre|\bmtr\b/', $h)) {
                $map['length_m'] = $i;
            } elseif (preg_match('/qty|quantity|length/', $h)) {
                $map['quantity'] ??= $i;
            } elseif (str_contains($h, 'roll')) {
                $map['vendor_roll_no'] = $i;
            }
        }

        $hasMetric = isset($map['quantity']) || isset($map['length_yd']) || isset($map['length_m']) || isset($map['weight']);

        return $hasMetric && count($map) >= 2 ? $map : null;
    }

    /**
     * Tolerant numeric parser: plain numbers, thousands separators
     * ("1,234.56"), and European comma decimals ("1.234,56" / "75,5").
     */
    public static function toNumber(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (! is_string($v)) {
            return null;
        }
        // Strip regular whitespace plus invisible unicode characters that
        // commonly slip in from copy-pasted PDF/web/spreadsheet data — a
        // non-breaking space (thousands separator in some Excel locales),
        // zero-width space, or BOM otherwise fails the digit regex below and
        // silently drops/rejects the row's quantity downstream.
        $s = preg_replace('/[\s\x{00A0}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]+/u', '', $v);
        if ($s === '' || ! preg_match('/^-?[\d.,]+$/', $s)) {
            return null;
        }
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $s) || preg_match('/^-?\d+,\d+$/', $s)) {
            // European format: dot thousands, comma decimal
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
