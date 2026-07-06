<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$dst = 'MPR/DST/2603/06363';
$dynamicKey = date('ymd', strtotime('-2 days'));

$urls = [
    'http://172.18.0.1:8071/process',
];

foreach ($urls as $url) {
    echo "Trying URL: $url\n";
    try {
        $response = Http::withHeaders([
            'X-API-Key' => 'DT-Secret-2026',
            'X-Dynamic-Key' => $dynamicKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'dst' => $dst,
        ]);

        echo 'Status: '.$response->status()."\n";
        echo 'Body: '.$response->body()."\n\n";
    } catch (\Exception $e) {
        echo 'Error: '.$e->getMessage()."\n\n";
    }
}
