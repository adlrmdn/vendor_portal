<?php

namespace App\Services;

use RuntimeException;

/**
 * Masks the roll code embedded in packing-slip QR codes so neither a glance
 * nor a scan reveals anything about it — output is opaque noise with no
 * visible structure, not a scrambled version of the original shape. Only
 * someone holding QR_ENCRYPTION_KEY (deliberately separate from APP_KEY so it
 * can be shared with an external scanning app without exposing this app's own
 * encryption key) can recover it.
 *
 * The roll code — always "PO-{period}-{poSeq}-{itemNumber}-{rollSeq}", period
 * always exactly 4 digits and poSeq always exactly 5 (verified: 3,634/3,634
 * live PO numbers match "PO-\d{4}-\d{5}"), itemNumber a 13-14 digit D365 code
 * with no leading zero (verified against every po_items row), rollSeq up to
 * 332 seen so far — is packed as true binary integers instead of left as
 * ASCII: period*100000+poSeq as a 31-bit int (fits with a spare top bit to
 * spare), itemNumber as a 48-bit int, rollSeq as 16 bits. 12 bytes total.
 * The spare top bit doubles as a format flag, so anything that doesn't fit
 * this shape (wrong digit counts, a leading zero, or a value too large for
 * its field) still round-trips correctly via a fallback that stores the
 * string verbatim instead — never misencodes/corrupts, just skips the
 * optimization for that one code.
 *
 * Those packed bytes are then XORed with a keyed deterministic keystream
 * (HMAC-SHA256(key, counter), chained) and hex-encoded (digits + capital
 * A-F only) — every language decodes it trivially, no custom alphabet
 * needed on your end. 12 bytes -> 24 chars for the common case above
 * (longer for the rare fallback).
 *
 * This is obfuscation, not authenticated encryption: the keystream depends
 * only on byte position, not per-code randomness, so it doesn't resist a
 * determined attacker who collects several known plaintext/masked pairs.
 * Deliberately traded away — this only needs to stop an at-a-glance read of a
 * printed label, not defend against forgery.
 */
class QrCodeCipher
{
    // Top bit of byte 0 doubles as the format flag: 0 = packed integers
    // (combined period+poSeq always fits in 31 bits, so this bit is never
    // legitimately set by the packed path), 1 = raw fallback.
    private const RAW_FLAG = 0x80;

    private const ITEM_MAX = 281474976710655; // 2^48 - 1

    private const ROLLSEQ_MAX = 65535; // 2^16 - 1

    public static function mask(string $rollCode): string
    {
        return strtoupper(bin2hex(self::xorKeystream(self::pack($rollCode))));
    }

    public static function unmask(string $masked): string
    {
        $bytes = @hex2bin(strtolower($masked));

        if ($bytes === false) {
            throw new RuntimeException('Malformed QR payload.');
        }

        return self::unpack(self::xorKeystream($bytes));
    }

    private static function xorKeystream(string $bytes): string
    {
        $keystream = self::keystreamBytes(strlen($bytes));
        $out = '';

        for ($i = 0; $i < strlen($bytes); $i++) {
            $out .= chr(ord($bytes[$i]) ^ ord($keystream[$i]));
        }

        return $out;
    }

    // HMAC-SHA256(key, "roll-mask:<counter>") chained until there are enough
    // bytes — deterministic, so the same key+position always yields the same
    // byte on both mask() and unmask() (XOR is its own inverse).
    private static function keystreamBytes(int $length): string
    {
        $key = self::key();
        $bytes = '';
        $counter = 0;

        while (strlen($bytes) < $length) {
            $bytes .= hash_hmac('sha256', 'roll-mask:'.$counter, $key, true);
            $counter++;
        }

        return substr($bytes, 0, $length);
    }

    private static function pack(string $plaintext): string
    {
        if (
            ! preg_match('/^PO-(\d{4})-(\d{5})-(\d+)-(\d+)$/', $plaintext, $m)
            || $m[3][0] === '0'
        ) {
            return chr(self::RAW_FLAG).$plaintext;
        }

        [, $period, $poSeq, $item, $rollSeq] = $m;
        $itemInt = (int) $item;
        $rollSeqInt = (int) $rollSeq;

        if ($itemInt > self::ITEM_MAX || $rollSeqInt > self::ROLLSEQ_MAX) {
            return chr(self::RAW_FLAG).$plaintext;
        }

        $combined = ((int) $period) * 100000 + (int) $poSeq; // max 1,099,999,999 — fits in 31 bits

        return self::packUint($combined, 4)
            .self::packUint($itemInt, 6)
            .self::packUint($rollSeqInt, 2);
    }

    private static function unpack(string $packed): string
    {
        if ($packed === '') {
            throw new RuntimeException('Malformed QR payload.');
        }

        if ((ord($packed[0]) & self::RAW_FLAG) !== 0) {
            return substr($packed, 1);
        }

        // The packed (non-raw) path is always exactly 12 bytes — a truncated
        // or corrupted scan must fail loudly here rather than silently
        // decoding to a wrong-but-well-formed-looking roll number.
        if (strlen($packed) !== 12) {
            throw new RuntimeException('Malformed QR payload.');
        }

        $combined = self::unpackUint(substr($packed, 0, 4));
        $itemInt = self::unpackUint(substr($packed, 4, 6));
        $rollSeqInt = self::unpackUint(substr($packed, 10, 2));

        return sprintf('PO-%04d-%05d-%d-%03d', intdiv($combined, 100000), $combined % 100000, $itemInt, $rollSeqInt);
    }

    // Big-endian, fixed-width unsigned integer, e.g. packUint(300, 2) = "\x01\x2C".
    private static function packUint(int $value, int $bytes): string
    {
        $out = '';
        for ($i = $bytes - 1; $i >= 0; $i--) {
            $out .= chr(($value >> ($i * 8)) & 0xFF);
        }

        return $out;
    }

    private static function unpackUint(string $bytes): int
    {
        $value = 0;
        for ($i = 0; $i < strlen($bytes); $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }

        return $value;
    }

    private static function key(): string
    {
        $key = base64_decode((string) config('services.qr_encryption_key'), true);

        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('QR_ENCRYPTION_KEY must be set to a base64-encoded 32-byte key.');
        }

        return $key;
    }
}
