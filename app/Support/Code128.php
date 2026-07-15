<?php

namespace App\Support;

/**
 * Renders a Code 128 barcode as a single PNG <img> (base64 data URI).
 *
 * Two reasons it is an image rather than font- or markup-based:
 *  - DomPDF cannot load remote web fonts, so a font barcode silently degrades
 *    to literal text.
 *  - Emitting the bars as hundreds of HTML <span> elements per label explodes
 *    DomPDF's frame tree; on a 100+ page run it exhausts memory. One <img>
 *    node per label keeps the render cheap.
 *
 * Encoding matches D365/tec-it "Code-128" auto mode: subset B, switching to
 * subset C for digit runs (two digits per symbol; an odd run leaves its FIRST
 * digit in subset B), plus the mandatory modulo-103 check symbol.
 *
 * Print fidelity: Code 128 uses four distinct bar widths, so the printed
 * label must preserve module proportions far more accurately than Code 39
 * (two widths, 1:3). The PNG is therefore rendered at 6 px per module — the
 * PDF downscale then distorts each module by at most ~1/6 — and a 10-module
 * quiet zone is baked into each side (spec minimum) so the scanner always
 * sees clean white shoulders regardless of surrounding label content.
 */
class Code128
{
    /**
     * Code 128 symbol patterns, indexed by symbol value 0–106. Each digit is
     * an element width in modules, alternating bar/space starting with a bar.
     * Symbols are 6 elements / 11 modules; the stop symbol (106) is
     * 7 elements / 13 modules.
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '2331112',
    ];

    private const CODE_TO_C = 99;  // switch symbol, from set B

    private const CODE_TO_B = 100; // switch symbol, from set C

    private const START_B = 104;

    private const START_C = 105;

    private const STOP = 106;

    private const QUIET_ZONE = 10; // modules of white on each side

    /**
     * Render the value as a Code 128 barcode <img> tag.
     *
     * @param  string  $value  Data to encode (chars outside ASCII 32–126 dropped).
     * @param  int  $displayH  Rendered bar height in px.
     * @param  float  $scale  Scaling factor to resize the barcode width.
     */
    public static function html(string $value, int $displayH = 42, int $maxDisplayW = 250, float $scale = 1.0): string
    {
        [$png, $modules] = self::png($value);
        $dataUri = 'data:image/png;base64,'.base64_encode($png);
        $displayW = min((int) round($modules * $scale), $maxDisplayW);

        return '<img src="'.$dataUri.'" width="'.$displayW.'" height="'.$displayH.'" alt="'.htmlspecialchars($value).'">';
    }

    /**
     * Encode the value into Code 128 symbol values (start/data/switch codes,
     * without checksum and stop).
     *
     * @return int[]
     */
    private static function symbols(string $value): array
    {
        $chars = array_values(array_filter(
            str_split($value),
            fn ($c) => ord($c) >= 32 && ord($c) <= 126
        ));

        $symbols = [];
        $set = null;
        $n = count($chars);
        $i = 0;
        while ($i < $n) {
            $run = 0;
            while ($i + $run < $n && ctype_digit($chars[$i + $run])) {
                $run++;
            }
            // Even count of digits encodable in subset C; an odd run keeps
            // its first digit in subset B (matches tec-it/D365 auto mode).
            $pairs = intdiv($run, 2) * 2;
            $atEnd = ($i + $run) === $n;
            $useC = $pairs >= 6
                || ($pairs >= 4 && ($i === 0 || $atEnd))
                || ($pairs >= 2 && $i === 0 && $atEnd);

            if ($useC && $run % 2 === 1) {
                if ($set === null) {
                    $symbols[] = self::START_B;
                    $set = 'B';
                }
                if ($set === 'B') {
                    $symbols[] = ord($chars[$i]) - 32;
                    $i++;
                } // if currently in C, pairs realign from the next digit
            }
            if ($useC) {
                if ($set === null) {
                    $symbols[] = self::START_C;
                } elseif ($set !== 'C') {
                    $symbols[] = self::CODE_TO_C;
                }
                $set = 'C';
                for ($j = 0; $j < $pairs; $j += 2) {
                    $symbols[] = (int) ($chars[$i + $j].$chars[$i + $j + 1]);
                }
                $i += $pairs;
            } else {
                if ($set === null) {
                    $symbols[] = self::START_B;
                } elseif ($set !== 'B') {
                    $symbols[] = self::CODE_TO_B;
                }
                $set = 'B';
                $symbols[] = ord($chars[$i]) - 32;
                $i++;
            }
        }
        if ($set === null) { // nothing encodable
            $symbols[] = self::START_B;
        }

        return $symbols;
    }

    /**
     * Build the barcode PNG.
     *
     * @return array{0:string,1:int} [raw PNG bytes, total module count incl. quiet zones]
     */
    private static function png(string $value): array
    {
        $scale = 6;   // source px per module (downscaled on display, stays crisp)
        $height = 60; // source px

        $symbols = self::symbols($value);

        $checksum = $symbols[0];
        foreach ($symbols as $i => $sym) {
            if ($i > 0) {
                $checksum += $sym * $i;
            }
        }
        $symbols[] = $checksum % 103;
        $symbols[] = self::STOP;

        $modules = (count($symbols) - 1) * 11 + 13 + 2 * self::QUIET_ZONE;
        $width = $modules * $scale;

        $img = imagecreate($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $x = self::QUIET_ZONE * $scale;
        foreach ($symbols as $sym) {
            $pattern = self::PATTERNS[$sym];
            $len = strlen($pattern);
            for ($p = 0; $p < $len; $p++) {
                $w = (int) $pattern[$p] * $scale;
                if ($p % 2 === 0) { // bar
                    imagefilledrectangle($img, $x, 0, $x + $w - 1, $height, $black);
                }
                $x += $w;
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return [$png, $modules];
    }
}
