<?php

declare(strict_types=1);

namespace Quoyer\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Quoyer\Laravel\Middleware\VerifyQuoyerWebhook;
use Quoyer\QuoyerClient;

/**
 * Auto-discovered. Binds one QuoyerClient built from `config/quoyer.php`,
 * the `Quoyer` facade and the `quoyer.webhook` middleware.
 *
 * The client is built on first use, so an app without QUOYER_API_KEY boots
 * fine and fails only where it actually calls Quoyer.
 */
final class QuoyerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/quoyer.php', 'quoyer');

        $this->app->singleton(QuoyerClient::class, static function (Application $app): QuoyerClient {
            /** @var array<string, mixed> $config */
            $config = (array) $app->make('config')->get('quoyer', []);

            $options = array_intersect_key($config, array_flip([
                'api_key', 'base_url', 'store_url', 'platform', 'timeout', 'connect_timeout', 'max_retries', 'max_retry_wait',
            ]));

            return new QuoyerClient(array_filter($options, static fn (mixed $value): bool => $value !== null && $value !== ''));
        });

        $this->app->alias(QuoyerClient::class, 'quoyer');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/quoyer.php' => $this->app->configPath('quoyer.php'),
            ], 'quoyer-config');
        }

        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('quoyer.webhook', VerifyQuoyerWebhook::class);
        });
    }
}
