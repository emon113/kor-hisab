<?php

namespace Database\Seeders;

use App\Models\Calculation;
use App\Models\User;
use App\Services\Tax\TaxReport;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Creates the owner account (from SEED_USER_* in .env) with one example calculation.
     * Safe to run more than once: an existing account is never overwritten.
     */
    public function run(): void
    {
        $config = config('korhishab.seed_user');
        $user = User::where('email', $config['email'])->first();

        if ($user) {
            $this->command?->info("User {$config['email']} already exists — password left unchanged.");
        } else {
            $password = $config['password'] ?: Str::password(16, symbols: false);
            $user = User::create([
                'name' => $config['name'],
                'email' => $config['email'],
                'password' => $password,
            ]);

            $this->command?->info("Created user {$user->email}.");
            if (! $config['password']) {
                $this->command?->warn("Generated password: {$password}");
                $this->command?->warn('Copy it now — it is not stored anywhere in plain text. Change it from the Account page.');
            }
        }

        if ($user->calculations()->doesntExist()) {
            $report = app(TaxReport::class)->build([
                'year' => config('tax.default_year'),
                'category' => 'general',
                'gross_income' => 1335524,
                'investments' => ['dps' => 120000],
                'tds_paid' => 0,
                'filing' => 'standard',
            ]);

            $user->calculations()->create(Calculation::attributesFromReport(
                $report,
                'My salary, AY 2026-27',
                'Example from the spreadsheet: ৳1,335,524 gross with ৳120,000 in DPS.'
            ));
        }
    }
}
