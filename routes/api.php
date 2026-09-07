<?php

use App\Http\Controllers\Api\QcVerificationEmailController;
use Illuminate\Support\Facades\Route;

// QC Console (Tauri desktop app) fallback dispatch: the console sends the
// Stage-1 "Verify & Sign" email directly over SMTP first (primary relay +
// Gmail backup, see forge/src-tauri/src/lib.rs); when the client machine's
// own network can't reach either relay (both legs fail, not just one), the
// console POSTs the same payload here so the server — which has working
// egress — sends it instead.
Route::post('/qc/send-verification-email', [QcVerificationEmailController::class, 'send']);
