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

$in_entity = false;
while (($line = fgets($fd)) !== false) {
    if (strpos($line, '<EntityType Name="RetailStore"') !== false) {
        $in_entity = true;
    }
    if ($in_entity) {
        if (preg_match('/<Property Name="([^"]+)"/', $line, $matches)) {
            echo 'Property: '.$matches[1]."\n";
        }
        if (strpos($line, '</EntityType>') !== false) {
            break;
        }
    }
}
fclose($fd);
