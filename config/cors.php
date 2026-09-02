<?php

/*
 * The Chimera QC Console (Tauri webview, origin http://tauri.localhost) POSTs
 * to the API path below as a network-fallback when its own direct SMTP send
 * (primary relay + Gmail backup) can't get out from the client machine.
 * Internal tool only — no browser session ever touches this endpoint.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['POST'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
