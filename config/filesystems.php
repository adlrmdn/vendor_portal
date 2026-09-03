<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        // Shared data lake used to offload heavy RPA queue payloads (see
        // RpaQueueService). No key/secret configured on purpose — the EC2
        // instance role (reachable from containers via IMDS) already has
        // read/write access to this bucket, same as the automaton host.
        'rpa_lake' => [
            'driver' => 's3',
            'region' => env('RPA_LAKE_S3_REGION', 'ap-southeast-3'),
            'bucket' => env('RPA_LAKE_S3_BUCKET', 'rpa-lake'),
            'root' => 'automaton',
            'throw' => true,
        ],

        // Subcon material-return delivery notes — material the SUBCON VENDOR
        // sends back to us (e.g. unused/excess cut fabric), NOT material we
        // return to our own upstream fabric supplier (a different, unbuilt
        // flow — don't reuse this disk/path for that if it's ever built).
        // Same bucket/instance-role access as rpa_lake, own prefix.
        'material_prod_return' => [
            'driver' => 's3',
            'region' => env('RPA_LAKE_S3_REGION', 'ap-southeast-3'),
            'bucket' => env('RPA_LAKE_S3_BUCKET', 'rpa-lake'),
            'root' => 'automaton/material_prod_return',
            'throw' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
