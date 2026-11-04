<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Data;

use Beziworld\Cpanel\Support\Values;
use DateTimeImmutable;

/**
 * One-time login (single sign-on) session created by the WHM function create_user_session.
 */
final readonly class UserSession
{
    public function __construct(
        public string $url,
        public string $service,
        public ?string $securityToken,
        public ?DateTimeImmutable $expiresAt,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $expires = Values::nullableInt($data, 'expires');

        return new self(
            url: Values::string($data, 'url'),
            service: Values::string($data, 'service', 'cpaneld'),
            securityToken: Values::nullableString($data, 'cp_security_token'),
            expiresAt: $expires === null ? null : new DateTimeImmutable('@' . $expires),
        );
    }
}
