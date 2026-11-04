<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Data\EmailAccount;
use Beziworld\Cpanel\Support\Values;

/**
 * Mailboxes and forwarders of a cPanel account (UAPI Email module).
 */
final class Email extends Resource
{
    /**
     * Mailboxes with disk usage and quota.
     *
     * @param string|null $domain Limits the list to one domain.
     * @return list<EmailAccount>
     */
    public function accounts(?string $domain = null): array
    {
        $rows = self::rows($this->connection->uapi('Email', 'list_pops_with_disk', ['domain' => $domain]));

        return array_map(EmailAccount::fromArray(...), $rows);
    }

    /**
     * Creates a mailbox and returns its address.
     *
     * @param string $email Full address ("office@example.com") or local part with $domain.
     * @param int|null $quotaMb Quota in megabytes; null or 0 means unlimited where allowed.
     */
    public function create(string $email, #[\SensitiveParameter] string $password, ?string $domain = null, ?int $quotaMb = null, bool $sendWelcomeEmail = false): string
    {
        [$user, $domain] = self::split($email, $domain);

        $this->connection->uapi('Email', 'add_pop', [
            'email' => $user,
            'domain' => $domain,
            'password' => $password,
            'quota' => $quotaMb,
            'send_welcome_email' => $sendWelcomeEmail,
        ], write: true);

        return $user . '@' . $domain;
    }

    public function delete(string $email, ?string $domain = null): void
    {
        [$user, $domain] = self::split($email, $domain);

        $this->connection->uapi('Email', 'delete_pop', ['email' => $user, 'domain' => $domain], write: true);
    }

    public function changePassword(string $email, #[\SensitiveParameter] string $password, ?string $domain = null): void
    {
        [$user, $domain] = self::split($email, $domain);

        $this->connection->uapi('Email', 'passwd_pop', ['email' => $user, 'domain' => $domain, 'password' => $password], write: true);
    }

    /**
     * @param int|null $quotaMb Quota in megabytes; null sets unlimited.
     */
    public function setQuota(string $email, ?int $quotaMb, ?string $domain = null): void
    {
        [$user, $domain] = self::split($email, $domain);

        $this->connection->uapi('Email', 'edit_pop_quota', [
            'email' => $user,
            'domain' => $domain,
            'quota' => $quotaMb ?? 'unlimited',
        ], write: true);
    }

    /**
     * Forwarders of a domain: list of ['dest' => 'office@example.com', 'forward' => 'anna@example.org', ...].
     *
     * @return list<array<array-key, mixed>>
     */
    public function forwarders(string $domain): array
    {
        return self::rows($this->connection->uapi('Email', 'list_forwarders', ['domain' => $domain]));
    }

    /**
     * Forwards mail sent to $address to $destination.
     */
    public function addForwarder(string $address, string $destination): void
    {
        [$user, $domain] = self::split($address, null);

        $this->connection->uapi('Email', 'add_forwarder', [
            'domain' => $domain,
            'email' => $user . '@' . $domain,
            'fwdopt' => 'fwd',
            'fwdemail' => $destination,
        ], write: true);
    }

    public function deleteForwarder(string $address, string $destination): void
    {
        $this->connection->uapi('Email', 'delete_forwarder', ['address' => $address, 'forwarder' => $destination], write: true);
    }

    /**
     * Disk usage of the account's mailboxes in megabytes, keyed by address.
     *
     * @return array<string, float>
     */
    public function diskUsage(): array
    {
        $usage = [];

        foreach ($this->accounts() as $account) {
            $usage[$account->email] = $account->diskUsedMb ?? 0.0;
        }

        return $usage;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function split(string $email, ?string $domain): array
    {
        if (str_contains($email, '@')) {
            [$user, $emailDomain] = explode('@', $email, 2);

            return [$user, $emailDomain];
        }

        if ($domain === null || $domain === '') {
            throw new \InvalidArgumentException(sprintf('Pass a full e-mail address or a domain for "%s".', $email));
        }

        return [$email, $domain];
    }

    /**
     * Raw quota information of a mailbox (UAPI Email::get_pop_quota) in megabytes.
     */
    public function quota(string $email, ?string $domain = null): ?float
    {
        [$user, $domain] = self::split($email, $domain);
        $quota = $this->connection->uapi('Email', 'get_pop_quota', ['email' => $user, 'domain' => $domain, 'as_bytes' => false]);

        return is_numeric($quota) ? (float) $quota : Values::nullableFloat(self::map($quota), 'quota');
    }
}
