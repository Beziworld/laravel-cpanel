<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

/**
 * SSL certificates and AutoSSL of a cPanel account (UAPI SSL module).
 */
final class Ssl extends Resource
{
    /**
     * Installs a certificate for a domain.
     *
     * @param string $certificate PEM certificate.
     * @param string|null $key PEM private key; cPanel looks it up when it is stored in the account.
     * @param string|null $caBundle PEM intermediate certificates.
     * @return array<array-key, mixed>
     */
    public function install(string $domain, string $certificate, #[\SensitiveParameter] ?string $key = null, ?string $caBundle = null): array
    {
        return self::map($this->connection->uapi('SSL', 'install_ssl', [
            'domain' => $domain,
            'cert' => $certificate,
            'key' => $key,
            'cabundle' => $caBundle,
        ], write: true));
    }

    /**
     * Starts an AutoSSL check for the account; certificates are issued asynchronously.
     */
    public function startAutoSsl(): void
    {
        $this->connection->uapi('SSL', 'start_autossl_check', write: true);
    }

    public function autoSslInProgress(): bool
    {
        $data = $this->connection->uapi('SSL', 'is_autossl_check_in_progress');

        return $data === 1 || $data === '1' || $data === true;
    }

    /**
     * Problems reported by the last AutoSSL run: list of ['domain' => ..., 'problem' => ..., 'time' => ...].
     *
     * @return list<array<array-key, mixed>>
     */
    public function autoSslProblems(): array
    {
        return self::rows($this->connection->uapi('SSL', 'get_autossl_problems'));
    }

    /**
     * Installed certificates per virtual host, with domains and expiry dates.
     *
     * @return list<array<array-key, mixed>>
     */
    public function installedHosts(): array
    {
        return self::rows($this->connection->uapi('SSL', 'installed_hosts'));
    }

    /**
     * Certificates stored in the account.
     *
     * @return list<array<array-key, mixed>>
     */
    public function certificates(): array
    {
        return self::rows($this->connection->uapi('SSL', 'list_certs'));
    }

    public function uninstall(string $domain): void
    {
        $this->connection->uapi('SSL', 'delete_ssl', ['domain' => $domain], write: true);
    }
}
