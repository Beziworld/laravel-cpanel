<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

/**
 * FTP accounts of a cPanel account (UAPI Ftp module).
 */
final class Ftp extends Resource
{
    /**
     * @return list<array<array-key, mixed>>
     */
    public function accounts(): array
    {
        return self::rows($this->connection->uapi('Ftp', 'list_ftp_with_disk'));
    }

    /**
     * @param string|null $homeDirectory Relative to the account home directory, e.g. "public_html/app".
     * @param int|null $quotaMb Quota in megabytes; null or 0 means unlimited.
     */
    public function create(string $user, #[\SensitiveParameter] string $password, ?string $domain = null, ?string $homeDirectory = null, ?int $quotaMb = null): void
    {
        $this->connection->uapi('Ftp', 'add_ftp', [
            'user' => $user,
            'pass' => $password,
            'domain' => $domain,
            'homedir' => $homeDirectory,
            'quota' => $quotaMb,
        ], write: true);
    }

    /**
     * @param bool $destroy Also deletes the FTP account's home directory.
     */
    public function delete(string $user, ?string $domain = null, bool $destroy = false): void
    {
        $this->connection->uapi('Ftp', 'delete_ftp', ['user' => $user, 'domain' => $domain, 'destroy' => $destroy], write: true);
    }

    public function changePassword(string $user, #[\SensitiveParameter] string $password, ?string $domain = null): void
    {
        $this->connection->uapi('Ftp', 'passwd', ['user' => $user, 'pass' => $password, 'domain' => $domain], write: true);
    }
}
