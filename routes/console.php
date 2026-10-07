<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// There is no password-reset email (no mail server needed), so this is the way back in:
//   sudo -u www-data php artisan user:password you@example.com
Artisan::command('user:password {email : Email of the account}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error("No account uses {$email}.");

        return 1;
    }

    $password = (string) $this->secret('New password (8+ characters, letters and numbers)');
    if (strlen($password) < 8 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
        $this->error('Use 8 or more characters with both letters and numbers.');

        return 1;
    }

    $user->update(['password' => $password]);
    $this->info("Password changed for {$email}.");

    return 0;
})->purpose('Set a new password for an account');
