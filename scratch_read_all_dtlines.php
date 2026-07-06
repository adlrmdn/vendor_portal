<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cmd = new \App\Console\Commands\SyncD365SubconOrders;
$ref = new ReflectionMethod($cmd, 'getD365Token');
$ref->setAccessible(true);
$token = $ref->invoke($cmd);

$resource = config('services.d365.resource');
$url = "{$resource}/data/DTLines";

echo "Querying all D365 DTLines by DTID...\n";
$response = Http::withToken($token)->acceptJson()->get($url, [
    'cross-company' => 'true',
    '$filter' => "DTID eq 'MPR/DST/2603/06363'",
]);

if ($response->successful()) {
    $lines = $response->json()['value'] ?? [];
    echo 'Fetched '.count($lines)." lines.\n";

    // Group lines by PackingCode and StoreID
    $grouped = [];
    foreach ($lines as $l) {
        $key = ($l['PackingCode'] ?: 'NO_PACKING_CODE').'_'.$l['StoreID'];
        $grouped[$key][] = $l;
    }

    foreach ($grouped as $key => $items) {
        echo "\nGroup Key: $key (Count: ".count($items).")\n";
        echo "Sample Item:\n";
        print_r($items[0]);
    }
} else {
    echo 'Query failed: '.$response->status()."\n";
}
