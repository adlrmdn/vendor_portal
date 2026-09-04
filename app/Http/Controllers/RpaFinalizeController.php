<?php

namespace App\Http\Controllers;

use App\Services\RpaQueueService;
use Illuminate\Http\Request;

/**
 * Machine-to-machine callback for automaton/pw_service/debit_note: once the
 * bot has scraped the real Document No/Invoice Date off the D365 journal it
 * just created, it calls this instead of overlaying those two fields onto
 * the existing PDF itself — the debit-note page gets genuinely re-rendered
 * through the same Blade template (RpaQueueService::regenerateDeductionDocs)
 * with the real values baked in, then re-offloaded to the same S3 key the
 * bot already knows to download from. No PHP session/CSRF involved; auth is
 * a single shared-secret header (config('services.rpa.finalize_token')) —
 * see .env's RPA_FINALIZE_TOKEN and this route's CSRF exemption in
 * bootstrap/app.php.
 */
class RpaFinalizeController extends Controller
{
    public function finalize(Request $request, int $id, RpaQueueService $rpa)
    {
        $token = (string) config('services.rpa.finalize_token');
        if ($token === '' || ! hash_equals($token, (string) $request->header('X-RPA-Token'))) {
            abort(403);
        }

        $documentNo = trim((string) $request->input('document_no'));
        $invoiceDate = trim((string) $request->input('invoice_date'));
        if ($documentNo === '' || $invoiceDate === '') {
            return response()->json(['message' => 'document_no and invoice_date are required.'], 422);
        }

        $ok = $rpa->regenerateDeductionDocs($id, $documentNo, $invoiceDate);
        if (! $ok) {
            return response()->json(['message' => 'Regeneration failed — see the Laravel log.'], 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
