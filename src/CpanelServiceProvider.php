<?php

declare(strict_types=1);

namespace Beziworld\Cpanel;

use Beziworld\Cpanel\Console\CheckConnectionCommand;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the cPanel connection manager and the console command.
 */
final class CpanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cpanel.php', 'cpanel');

        $this->app->singleton(CpanelManager::class, static fn(Application $app): CpanelManager => new CpanelManager(
            $app->make(Config::class),
            $app->make(HttpFactory::class),
        ));

        $this->app->bind(CpanelClient::class, static fn(Application $app): CpanelClient => $app->make(CpanelManager::class)->connection());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/cpanel.php' => $this->app->configPath('cpanel.php'),
            ], 'cpanel-config');

            $this->commands([CheckConnectionCommand::class]);
        }
    }
}
