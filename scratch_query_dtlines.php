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

echo "Querying D365 DTLines by DTID...\n";
$response = Http::withToken($token)->acceptJson()->get($url, [
    'cross-company' => 'true',
    '$filter' => "DTID eq 'MPR/DST/2603/06363'",
    '$top' => 3,
]);

echo 'Status: '.$response->status()."\n";
echo 'Body: '.json_encode($response->json(), JSON_PRETTY_PRINT)."\n";
