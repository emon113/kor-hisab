<?php

namespace App\Providers;

use App\Services\Tax\TaxEngine;
use App\Services\Tax\TaxReport;
use App\Services\Tax\TaxTips;
use App\Services\Tax\TdsPlanner;
use App\Services\Wealth\WealthReconciler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The engine is stateless; TaxReport and TargetTaxSolver are autowired with it.
        $this->app->singleton(TaxEngine::class, fn ($app) => new TaxEngine($app['config']->get('tax')));
        $this->app->bind(TdsPlanner::class, fn ($app) => new TdsPlanner(
            $app->make(TaxEngine::class), $app->make(TaxReport::class), array_keys($app['config']->get('salary.perks')),
        ));
        $this->app->bind(TaxTips::class, fn ($app) => new TaxTips($app->make(TaxEngine::class), $app['config']->get('tips')));
        $this->app->singleton(WealthReconciler::class, fn ($app) => new WealthReconciler($app['config']->get('wealth')));

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
