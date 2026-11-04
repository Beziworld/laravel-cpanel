<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Data;

use InvalidArgumentException;

/**
 * Input of the WHM function createacct.
 */
final readonly class AccountRequest
{
    /**
     * @param array<string, scalar> $extra Any other createacct parameter, e.g. ['maxpop' => 10, 'hasshell' => true].
     */
    public function __construct(
        public string $username,
        public string $domain,
        #[\SensitiveParameter]
        public ?string $password = null,
        public ?string $package = null,
        public ?string $contactEmail = null,
        public ?bool $dedicatedIp = null,
        public ?string $owner = null,
        public ?int $quotaMb = null,
        public array $extra = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9]{0,15}$/', $username) !== 1) {
            throw new InvalidArgumentException('cPanel usernames start with a lowercase letter and contain up to 16 lowercase letters and digits.');
        }

        if ($contactEmail !== null && filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid e-mail address.', $contactEmail));
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toParameters(): array
    {
        return [
            'username' => $this->username,
            'domain' => $this->domain,
            'password' => $this->password,
            'plan' => $this->package,
            'contactemail' => $this->contactEmail,
            'ip' => $this->dedicatedIp === null ? null : ($this->dedicatedIp ? 'y' : 'n'),
            'owner' => $this->owner,
            'quota' => $this->quotaMb,
            ...$this->extra,
        ];
    }
}
