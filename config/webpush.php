<?php

return [
    // VAPID identifies this server to the push services. Generate a pair once
    // with `php artisan push:vapid` and paste both values into .env; the public
    // key is also handed to the browser, the private key must never leave here.
    'vapid' => [
        'subject'     => env('VAPID_SUBJECT', env('APP_URL', 'http://localhost')),
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // Time-to-live in seconds for a queued notification at the push service.
    'ttl' => (int) env('WEBPUSH_TTL', 43200),
];
