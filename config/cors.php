<?php

/*
 * The Chimera QC Console (Tauri webview, origin http://tauri.localhost) fetches
 * the inspection PDF from these GET endpoints at Verify → Send time. Without
 * CORS headers the webview blocks the response and the verify email cannot be
 * sent. Both endpoints are read-only and already reachable unauthenticated.
 */
return [
    'paths' => ['qc/print/*', 'qc/document/*'],
    'allowed_methods' => ['GET'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
