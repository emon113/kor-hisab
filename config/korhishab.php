<?php

return [
    // Account created by `php artisan db:seed`. Set these in .env before seeding.
    // If SEED_USER_PASSWORD is empty, a strong random password is generated and printed once.
    'seed_user' => [
        'name' => env('SEED_USER_NAME', 'Emon'),
        'email' => env('SEED_USER_EMAIL', 'emon@example.com'),
        'password' => env('SEED_USER_PASSWORD'),
    ],
];
