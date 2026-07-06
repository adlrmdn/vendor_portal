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

echo "Querying D365 DTLines with non-empty PackingCode...\n";
$response = Http::withToken($token)->acceptJson()->get($url, [
    'cross-company' => 'true',
    '$filter' => "PackingCode ne ''",
    '$top' => 5,
]);

echo 'Status: '.$response->status()."\n";
if ($response->successful()) {
    echo 'Body: '.json_encode($response->json(), JSON_PRETTY_PRINT)."\n";
} else {
    echo 'Error: '.$response->body()."\n";
}
