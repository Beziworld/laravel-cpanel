<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Data;

use Beziworld\Cpanel\Support\Values;

/**
 * Hosting package (plan) as returned by the WHM function listpkgs.
 *
 * Limits are strings because WHM reports them either as numbers or as "unlimited".
 */
final readonly class HostingPackage
{
    /**
     * @param array<array-key, mixed> $raw Complete record returned by WHM.
     */
    public function __construct(
        public string $name,
        public ?string $diskQuota,
        public ?string $bandwidthLimit,
        public ?string $maxEmailAccounts,
        public ?string $maxDatabases,
        public ?string $maxAddonDomains,
        public ?string $maxSubdomains,
        public ?string $featureList,
        public bool $shellAccess,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Values::string($data, 'name'),
            diskQuota: Values::nullableString($data, 'QUOTA'),
            bandwidthLimit: Values::nullableString($data, 'BWLIMIT'),
            maxEmailAccounts: Values::nullableString($data, 'MAXPOP'),
            maxDatabases: Values::nullableString($data, 'MAXSQL'),
            maxAddonDomains: Values::nullableString($data, 'MAXADDON'),
            maxSubdomains: Values::nullableString($data, 'MAXSUB'),
            featureList: Values::nullableString($data, 'FEATURELIST'),
            shellAccess: Values::bool($data, 'HASSHELL'),
            raw: $data,
        );
    }
}
