<?php

namespace App\Providers;

use App\Domain\Finance\Repositories\RepositorioDeLancamentos;
use App\Infra\Eloquent\RepositorioDeLancamentosEloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RepositorioDeLancamentos::class, RepositorioDeLancamentosEloquent::class);

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventAccessingMissingAttributes();
        Model::preventSilentlyDiscardingAttributes();
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
