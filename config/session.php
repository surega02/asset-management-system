<?php

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => 30,
    'encrypt' => true,
    'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'),
    'http_only' => true,
    'same_site' => 'lax',
];
