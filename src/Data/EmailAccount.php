<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Data;

use Beziworld\Cpanel\Support\Values;

/**
 * Mailbox as returned by the UAPI function Email::list_pops_with_disk.
 */
final readonly class EmailAccount
{
    /**
     * @param array<array-key, mixed> $raw Complete record returned by UAPI.
     */
    public function __construct(
        public string $email,
        public string $user,
        public string $domain,
        public ?float $diskUsedMb,
        public ?float $quotaMb,
        public bool $suspendedLogin,
        public bool $suspendedIncoming,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $email = Values::string($data, 'email');
        $parts = explode('@', $email, 2);
        $quota = Values::nullableFloat($data, 'diskquota');

        return new self(
            email: $email,
            user: Values::string($data, 'user', $parts[0]),
            domain: Values::string($data, 'domain', $parts[1] ?? ''),
            diskUsedMb: Values::nullableFloat($data, 'diskused'),
            quotaMb: $quota === 0.0 ? null : $quota,
            suspendedLogin: Values::bool($data, 'suspended_login'),
            suspendedIncoming: Values::bool($data, 'suspended_incoming'),
            raw: $data,
        );
    }
}
