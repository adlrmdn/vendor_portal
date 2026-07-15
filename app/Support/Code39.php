<?php

namespace App\Support;

/**
 * Renders a Code 39 barcode as a single PNG <img> (base64 data URI).
 *
 * Two reasons it is an image rather than font- or markup-based:
 *  - DomPDF cannot load remote web fonts (e.g. Google "Libre Barcode 39"), so a
 *    font barcode silently degrades to literal text.
 *  - Emitting the bars as hundreds of HTML <span> elements per label explodes
 *    DomPDF's frame tree; on a 100+ page run it exhausts memory. One <img>
 *    node per label keeps the render cheap.
 */
class Code39
{
    /**
     * Code 39 patterns: 9 elements per char, alternating bar/space starting
     * with a bar. 'n' = narrow, 'w' = wide.
     */
    private const PATTERNS = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
    ];

    /**
     * Render the value as a Code 39 barcode <img> tag.
     *
     * @param  string  $value  Data to encode (auto-uppercased; unsupported chars dropped).
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
     * Build the barcode PNG.
     *
     * @return array{0:string,1:int} [raw PNG bytes, total module count]
     */
    private static function png(string $value): array
    {
        $value = strtoupper($value);
        $scale = 2;   // source px per narrow module (downscaled on display, stays crisp)
        $height = 60; // source px

        $chars = ['*'];
        foreach (str_split($value) as $c) {
            if (isset(self::PATTERNS[$c])) {
                $chars[] = $c;
            }
        }
        $chars[] = '*';

        // Total modules: per char 6 narrow + 3 wide(=3) = 15, plus a 1-module
        // inter-char gap after all but the last char.
        $modules = count($chars) * 15 + (count($chars) - 1);
        $width = $modules * $scale;

        $img = imagecreate($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $x = 0;
        foreach ($chars as $i => $char) {
            $pattern = self::PATTERNS[$char];
            for ($p = 0; $p < 9; $p++) {
                $w = (($pattern[$p] === 'w') ? 3 : 1) * $scale;
                if ($p % 2 === 0) { // bar
                    imagefilledrectangle($img, $x, 0, $x + $w - 1, $height, $black);
                }
                $x += $w;
            }
            if ($i < count($chars) - 1) {
                $x += $scale; // inter-char gap
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return [$png, $modules];
    }
}
