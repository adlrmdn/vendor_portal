<?php

/*
 * The Chimera QC Console (Tauri webview, origin http://tauri.localhost) fetches
 * the inspection PDF from these GET endpoints at Verify → Send time. Without
 * CORS headers the webview blocks the response and the verify email cannot be
 * sent. Both endpoints are read-only and already reachable unauthenticated.
 *
 * api/* covers POST /api/qc/send-verification-email — the console's fallback
 * dispatch when its own direct SMTP send (primary relay + Gmail backup) can't
 * get out from the client machine.
 */
return [
    'paths' => ['qc/print/*', 'qc/document/*', 'api/*'],
    'allowed_methods' => ['GET', 'POST'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
