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
$url = "{$resource}/data/\$metadata";

echo "Streaming entity types from D365...\n";
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer $token\r\nAccept: application/xml\r\n",
        'timeout' => 60,
    ],
];
$context = stream_context_create($opts);
$fd = fopen($url, 'r', false, $context);
if (! $fd) {
    exit("Failed to open stream\n");
}

$entityTypes = [];
while (($line = fgets($fd)) !== false) {
    if (preg_match('/<EntityType Name="([^"]+)"/', $line, $matches)) {
        $name = $matches[1];
        if (stripos($name, 'TOC_') !== false || stripos($name, 'DTLine') !== false || (stripos($name, 'DT') !== false && (stripos($name, 'line') !== false || stripos($name, 'detail') !== false))) {
            $entityTypes[] = $name;
        }
    }
}
fclose($fd);

echo "Found matches:\n";
print_r($entityTypes);
