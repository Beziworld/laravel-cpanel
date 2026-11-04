<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Data;

use Beziworld\Cpanel\Support\Values;

/**
 * MySQL or MariaDB database as returned by the UAPI function Mysql::list_databases.
 */
final readonly class Database
{
    /**
     * @param list<string> $users Database users with privileges on the database.
     */
    public function __construct(
        public string $name,
        public int $diskUsage,
        public array $users = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Values::string($data, 'database'),
            diskUsage: Values::int($data, 'disk_usage'),
            users: Values::strings($data, 'users'),
        );
    }
}
