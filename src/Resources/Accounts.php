<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Data\Account;
use Beziworld\Cpanel\Data\AccountRequest;
use Beziworld\Cpanel\Exceptions\ApiException;

/**
 * cPanel accounts managed through WHM API 1 (root or reseller token).
 */
final class Accounts extends Resource
{
    /**
     * Accounts visible to the token, optionally filtered.
     *
     * @param string|null $search Search term, e.g. a username, domain or package.
     * @param string $searchType One of: user, domain, owner, ip, package.
     * @return list<Account>
     */
    public function list(?string $search = null, string $searchType = 'user', bool $exact = false): array
    {
        $data = $this->connection->whm('listaccts', [
            'search' => $search,
            'searchtype' => $search === null ? null : $searchType,
            'searchmethod' => $search === null ? null : ($exact ? 'exact' : 'regex'),
        ]);

        return array_map(Account::fromArray(...), self::rows($data['acct'] ?? []));
    }

    /**
     * Returns the account with the given username, or null when it does not exist.
     */
    public function find(string $username): ?Account
    {
        return $this->list($username, 'user', true)[0] ?? null;
    }

    /**
     * Summary of a single account (WHM accountsummary).
     *
     * @throws ApiException When the account does not exist.
     */
    public function summary(string $username): Account
    {
        $rows = self::rows($this->connection->whm('accountsummary', ['user' => $username])['acct'] ?? []);

        if ($rows === []) {
            throw ApiException::failed('accountsummary', ['No account data returned for ' . $username], 200, []);
        }

        return Account::fromArray($rows[0]);
    }

    /**
     * Creates an account; the response contains the assigned IP address, package and name servers.
     *
     * @return array<array-key, mixed>
     */
    public function create(AccountRequest $request): array
    {
        return $this->connection->whm('createacct', $request->toParameters(), write: true);
    }

    /**
     * Terminates the account together with all its data. This cannot be undone.
     */
    public function remove(string $username, bool $keepDns = false): void
    {
        $this->connection->whm('removeacct', ['username' => $username, 'keepdns' => $keepDns], write: true);
    }

    /**
     * @param bool $disallowUnsuspend Prevents resellers from unsuspending the account.
     */
    public function suspend(string $username, ?string $reason = null, bool $disallowUnsuspend = false): void
    {
        $this->connection->whm('suspendacct', [
            'user' => $username,
            'reason' => $reason,
            'disallowun' => $disallowUnsuspend ? true : null,
        ], write: true);
    }

    public function unsuspend(string $username): void
    {
        $this->connection->whm('unsuspendacct', ['user' => $username], write: true);
    }

    /**
     * Updates account settings with modifyacct.
     *
     * @param array<string, scalar|null> $changes WHM keys, e.g. ['QUOTA' => 2048, 'MAXPOP' => 50, 'contactemail' => '...'].
     */
    public function modify(string $username, array $changes): void
    {
        $this->connection->whm('modifyacct', ['user' => $username] + $changes, write: true);
    }

    /**
     * Assigns a hosting package (plan) to the account.
     */
    public function changePackage(string $username, string $package): void
    {
        $this->connection->whm('changepackage', ['user' => $username, 'pkg' => $package], write: true);
    }

    /**
     * Changes the account password; optionally also the MySQL password of the account.
     */
    public function changePassword(string $username, #[\SensitiveParameter] string $password, bool $updateDatabasePassword = false): void
    {
        $this->connection->whm('passwd', [
            'user' => $username,
            'password' => $password,
            'db_pass_update' => $updateDatabasePassword,
        ], write: true);
    }
}
