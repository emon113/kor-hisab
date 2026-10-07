<?php

namespace App\Providers;

use App\Services\Tax\TaxEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The engine is stateless; TaxReport and TargetTaxSolver are autowired with it.
        $this->app->singleton(TaxEngine::class, fn ($app) => new TaxEngine($app['config']->get('tax')));

        // SQLite: write-ahead logging lets reads continue during writes; wait instead of failing when busy.
        $config = $this->app['config'];
        foreach (['busy_timeout' => 5000, 'journal_mode' => 'wal', 'synchronous' => 'normal'] as $key => $value) {
            if ($config->get("database.connections.sqlite.{$key}") === null) {
                $config->set("database.connections.sqlite.{$key}", $value);
            }
        }
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
