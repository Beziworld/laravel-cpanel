<?php

declare(strict_types=1);

namespace Beziworld\Cpanel;

use Beziworld\Cpanel\Resources\Accounts;
use Beziworld\Cpanel\Resources\Backups;
use Beziworld\Cpanel\Resources\Cron;
use Beziworld\Cpanel\Resources\Dns;
use Beziworld\Cpanel\Resources\Domains;
use Beziworld\Cpanel\Resources\Email;
use Beziworld\Cpanel\Resources\Files;
use Beziworld\Cpanel\Resources\Ftp;
use Beziworld\Cpanel\Resources\Mysql;
use Beziworld\Cpanel\Resources\Packages;
use Beziworld\Cpanel\Resources\Sessions;
use Beziworld\Cpanel\Resources\Ssl;
use Beziworld\Cpanel\Resources\Stats;

/**
 * Entry point to a single cPanel or WHM server, grouping the API by topic.
 *
 * WHM resources (accounts, packages, sessions) need a connection of type
 * "whm". Account resources (domains, e-mail, MySQL, ...) work on a "cpanel"
 * connection directly, or on a WHM connection after forUser('username').
 */
class CpanelClient
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Returns a client executing UAPI and API 2 calls as the given cPanel account (WHM connections only).
     */
    public function forUser(string $username): self
    {
        return new self($this->connection->forAccount($username));
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function accounts(): Accounts
    {
        return new Accounts($this->connection);
    }

    public function packages(): Packages
    {
        return new Packages($this->connection);
    }

    public function sessions(): Sessions
    {
        return new Sessions($this->connection);
    }

    public function domains(): Domains
    {
        return new Domains($this->connection);
    }

    public function dns(): Dns
    {
        return new Dns($this->connection);
    }

    public function email(): Email
    {
        return new Email($this->connection);
    }

    public function mysql(): Mysql
    {
        return new Mysql($this->connection);
    }

    public function ssl(): Ssl
    {
        return new Ssl($this->connection);
    }

    public function ftp(): Ftp
    {
        return new Ftp($this->connection);
    }

    public function cron(): Cron
    {
        return new Cron($this->connection);
    }

    public function files(): Files
    {
        return new Files($this->connection);
    }

    public function backups(): Backups
    {
        return new Backups($this->connection);
    }

    public function stats(): Stats
    {
        return new Stats($this->connection);
    }

    /**
     * Calls any WHM API 1 function and returns its "data" member.
     *
     * @param array<string, scalar|list<scalar>|null> $parameters
     * @param bool $write Sends the parameters as a POST form, for functions that change state.
     * @return array<array-key, mixed>
     */
    public function whm(string $function, array $parameters = [], bool $write = false): array
    {
        return $this->connection->whm($function, $parameters, $write);
    }

    /**
     * Calls any UAPI function and returns its "data" member.
     *
     * @param array<string, scalar|list<scalar>|null> $parameters
     */
    public function uapi(string $module, string $function, array $parameters = [], bool $write = false): mixed
    {
        return $this->connection->uapi($module, $function, $parameters, $write);
    }

    /**
     * Calls any legacy cPanel API 2 function and returns its "data" member.
     *
     * @param array<string, scalar|list<scalar>|null> $parameters
     * @return array<array-key, mixed>
     */
    public function api2(string $module, string $function, array $parameters = [], bool $write = false): array
    {
        return $this->connection->api2($module, $function, $parameters, $write);
    }
}
