<?php

namespace JohnGuoy\LaravelX402;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JohnGuoy\LaravelX402\Http\Middleware\X402Payment;
use JohnGuoy\LaravelX402\Support\HttpFacilitator;
use JohnGuoy\LaravelX402\Support\PaymentAmount;
use JohnGuoy\LaravelX402\Support\PaymentRequirement;
use JohnGuoy\LaravelX402\Support\X402Manager;

class X402ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config so callers can override individual keys
        $this->mergeConfigFrom(__DIR__.'/../config/x402.php', 'x402');

        // Singletons ─────────────────────────────────────────────────────────
        $this->app->singleton(PaymentAmount::class);

        $this->app->singleton(PaymentRequirement::class, function ($app) {
            return new PaymentRequirement($app->make(PaymentAmount::class));
        });

        $this->app->singleton(X402Manager::class);

        // Alias for Facade resolution
        $this->app->alias(X402Manager::class, 'x402');
    }

    public function boot(): void
    {
        // ── Config publishing ────────────────────────────────────────────────
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/x402.php' => config_path('x402.php'),
            ], 'x402-config');
        }

        // ── Middleware alias ─────────────────────────────────────────────────
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('x402', X402Payment::class);
    }
}
