<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Data\Database;

/**
 * MySQL and MariaDB databases and users of a cPanel account (UAPI Mysql module).
 *
 * Names must include the account prefix (e.g. "john_shop") when database
 * prefixing is enabled on the server.
 */
final class Mysql extends Resource
{
    /**
     * @return list<Database>
     */
    public function databases(): array
    {
        return array_map(Database::fromArray(...), self::rows($this->connection->uapi('Mysql', 'list_databases')));
    }

    /**
     * Database users: list of ['user' => 'john_shop', 'databases' => [...], ...].
     *
     * @return list<array<array-key, mixed>>
     */
    public function users(): array
    {
        return self::rows($this->connection->uapi('Mysql', 'list_users'));
    }

    public function createDatabase(string $name): void
    {
        $this->connection->uapi('Mysql', 'create_database', ['name' => $name], write: true);
    }

    public function createUser(string $name, #[\SensitiveParameter] string $password): void
    {
        $this->connection->uapi('Mysql', 'create_user', ['name' => $name, 'password' => $password], write: true);
    }

    /**
     * Grants privileges on a database to a user.
     *
     * @param string $privileges "ALL PRIVILEGES" or a comma-separated list, e.g. "SELECT,INSERT,UPDATE,DELETE".
     */
    public function grant(string $user, string $database, string $privileges = 'ALL PRIVILEGES'): void
    {
        $this->connection->uapi('Mysql', 'set_privileges_on_database', [
            'user' => $user,
            'database' => $database,
            'privileges' => $privileges,
        ], write: true);
    }

    public function revoke(string $user, string $database): void
    {
        $this->connection->uapi('Mysql', 'revoke_access_to_database', ['user' => $user, 'database' => $database], write: true);
    }

    public function changePassword(string $user, #[\SensitiveParameter] string $password): void
    {
        $this->connection->uapi('Mysql', 'set_password', ['user' => $user, 'password' => $password], write: true);
    }

    public function deleteDatabase(string $name): void
    {
        $this->connection->uapi('Mysql', 'delete_database', ['name' => $name], write: true);
    }

    public function deleteUser(string $name): void
    {
        $this->connection->uapi('Mysql', 'delete_user', ['name' => $name], write: true);
    }

    /**
     * Creates a database, a user and grants the user all privileges on the database.
     *
     * The three calls are not atomic; when a later call fails, the objects
     * created before remain and the exception is rethrown unchanged.
     */
    public function createWithUser(string $database, string $user, #[\SensitiveParameter] string $password): void
    {
        $this->createDatabase($database);
        $this->createUser($user, $password);
        $this->grant($user, $database);
    }
}
