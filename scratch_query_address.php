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
$url = "{$resource}/data/PartyLocationPostalAddressV2";

echo "Querying PartyLocationPostalAddressV2...\n";
$response = Http::withToken($token)->acceptJson()->get($url, [
    'cross-company' => 'true',
    '$filter' => "substringof('SUPERMAL KARAWACI', Description) or substringof('SUPERMAL KARAWACI', Street)",
    '$top' => 3,
]);

echo 'Status: '.$response->status()."\n";
echo 'Body: '.json_encode($response->json(), JSON_PRETTY_PRINT)."\n";
