<?php

return [
    'disks' => [
        'asset-private' => [
            'driver' => 'local',
            'root' => storage_path('app/private/assets'),
            'visibility' => 'private',
            'throw' => true,
        ],
    ],
];
