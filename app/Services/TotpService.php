<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * TOTP (RFC 6238) step-up code for PC Approver/Admin actions (PC spec
 * section 12). Phase 1 only builds this capability — setup + verification
 * — nothing in Phase 1 calls verifyTotpCode() yet, since there's no
 * Approve action until contracts exist (Phase 2/3).
 */
class TotpService
{
    protected Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA;
    }

    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * Inline SVG QR code for an authenticator app to scan, same
     * QrCode::format('svg') call shape as AdminController::rollQrCode.
     */
    public function qrCodeSvg(string $email, string $secret): string
    {
        $issuer = rawurlencode(config('app.name', 'Vendor Portal').' PC');
        $label = rawurlencode($email);
        $uri = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}";

        return QrCode::format('svg')->size(220)->generate($uri);
    }

    public function verifyTotpCode(string $secret, string $code): bool
    {
        return $this->engine->verifyKey($secret, $code) !== false;
    }
}
