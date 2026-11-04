<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Data;

use Beziworld\Cpanel\Support\Values;

/**
 * cPanel account as returned by the WHM functions listaccts and accountsummary.
 */
final readonly class Account
{
    /**
     * @param array<array-key, mixed> $raw Complete record returned by WHM.
     */
    public function __construct(
        public string $username,
        public string $domain,
        public ?string $email,
        public ?string $package,
        public ?string $ip,
        public ?string $owner,
        public bool $suspended,
        public ?string $suspendReason,
        public ?string $diskUsed,
        public ?string $diskLimit,
        public ?int $createdAt,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $suspended = Values::bool($data, 'suspended');

        return new self(
            username: Values::string($data, 'user'),
            domain: Values::string($data, 'domain'),
            email: Values::nullableString($data, 'email'),
            package: Values::nullableString($data, 'plan'),
            ip: Values::nullableString($data, 'ip'),
            owner: Values::nullableString($data, 'owner'),
            suspended: $suspended,
            suspendReason: $suspended ? Values::nullableString($data, 'suspendreason') : null,
            diskUsed: Values::nullableString($data, 'diskused'),
            diskLimit: Values::nullableString($data, 'disklimit'),
            createdAt: Values::nullableInt($data, 'unix_startdate'),
            raw: $data,
        );
    }
}
