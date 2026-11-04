<?php

declare(strict_types=1);

namespace Beziworld\Cpanel;

use BadMethodCallException;
use Beziworld\Cpanel\Exceptions\InvalidConfiguration;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Resolves cPanel and WHM clients for any number of servers.
 *
 * Named connections are defined in config/cpanel.php in the same way as
 * database connections; options outside "connections" (verify, timeout,
 * connect_timeout) are shared defaults. Clients for credentials stored at
 * runtime, for example servers kept in the database, are created with
 * using(). Calls made directly on the manager go to the default connection.
 *
 * @mixin CpanelClient
 */
class CpanelManager
{
    /** @var array<string, CpanelClient> */
    private array $clients = [];

    public function __construct(
        private readonly Config $config,
        private readonly HttpFactory $http,
    ) {}

    /**
     * Client of a configured connection; the default connection when no name is given.
     *
     * @throws InvalidConfiguration When the connection is not defined or incomplete.
     */
    public function connection(?string $name = null): CpanelClient
    {
        $name ??= $this->defaultConnection();

        return $this->clients[$name] ??= $this->resolve($name);
    }

    /**
     * Client for credentials provided at runtime, merged with the shared options.
     *
     * @param ConnectionConfig|array<string, mixed> $config Keys as in a connection: host, username, token, type, verify, ...
     */
    public function using(ConnectionConfig|array $config, string $name = 'runtime'): CpanelClient
    {
        $config = $config instanceof ConnectionConfig ? $config : ConnectionConfig::fromArray($name, self::merge($this->shared(), $config));

        return new CpanelClient(new Connection($this->http, $config));
    }

    public function defaultConnection(): string
    {
        $default = $this->config->get('cpanel.default');

        return is_string($default) && $default !== '' ? $default : 'default';
    }

    /**
     * Names of the configured connections.
     *
     * @return list<string>
     */
    public function connectionNames(): array
    {
        $connections = $this->config->get('cpanel.connections');

        return is_array($connections) ? array_map(strval(...), array_keys($connections)) : [];
    }

    /**
     * Forgets resolved clients so that they are rebuilt from the configuration.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->clients = [];

            return;
        }

        unset($this->clients[$name]);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $callable = [$this->connection(), $method];

        if (! is_callable($callable)) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', CpanelClient::class, $method));
        }

        return $callable(...$arguments);
    }

    private function resolve(string $name): CpanelClient
    {
        $config = $this->config->get('cpanel.connections.' . $name);

        if (! is_array($config)) {
            throw InvalidConfiguration::unknownConnection($name);
        }

        return new CpanelClient(new Connection($this->http, ConnectionConfig::fromArray($name, self::merge($this->shared(), $config))));
    }

    /**
     * Applies connection options over the shared ones; empty values (unset environment variables) do not override.
     *
     * @param array<string, mixed> $shared
     * @param array<array-key, mixed> $connection
     * @return array<string, mixed>
     */
    private static function merge(array $shared, array $connection): array
    {
        foreach ($connection as $key => $value) {
            if ($value !== null && $value !== '') {
                $shared[(string) $key] = $value;
            }
        }

        return $shared;
    }

    /**
     * Options shared by every connection: everything outside "default" and "connections".
     *
     * @return array<string, mixed>
     */
    private function shared(): array
    {
        $config = $this->config->get('cpanel', []);
        $config = is_array($config) ? $config : [];
        unset($config['default'], $config['connections']);

        /** @var array<string, mixed> $config */
        return $config;
    }
}
