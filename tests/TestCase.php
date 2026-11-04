<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Tests;

use Beziworld\Cpanel\CpanelManager;
use Beziworld\Cpanel\CpanelServiceProvider;
use Beziworld\Cpanel\Facades\Cpanel;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const string WHM = 'https://whm.example.com:2087';

    public const string CPANEL = 'https://shared.example.com:2083';

    protected function getPackageProviders($app): array
    {
        return [CpanelServiceProvider::class];
    }

    /**
     * Replaces all previously registered HTTP stubs instead of appending to them.
     *
     * @param array<string, mixed> $stubs
     */
    public static function refake(array $stubs): void
    {
        Http::swap(new Factory(app('events')));
        Http::fake($stubs);

        app()->forgetInstance(CpanelManager::class);
        Cpanel::clearResolvedInstances();
    }

    /**
     * WHM API 1 success envelope.
     *
     * @param array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    public static function whmOk(string $command, array $data = []): array
    {
        return ['data' => $data, 'metadata' => ['command' => $command, 'reason' => 'OK', 'result' => 1, 'version' => 1]];
    }

    /**
     * UAPI success envelope.
     *
     * @return array<string, mixed>
     */
    public static function uapiOk(mixed $data = null): array
    {
        return ['status' => 1, 'errors' => null, 'messages' => null, 'warnings' => null, 'metadata' => [], 'data' => $data];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cpanel.connections.default', [
            'type' => 'whm',
            'host' => 'whm.example.com',
            'username' => 'root',
            'token' => 'WHMTOKEN',
        ]);
        $app['config']->set('cpanel.connections.shared', [
            'type' => 'cpanel',
            'host' => 'https://shared.example.com',
            'username' => 'john',
            'token' => 'CPTOKEN',
            'verify' => 'false',
        ]);
    }
}
