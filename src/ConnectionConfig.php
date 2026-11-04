<?php

declare(strict_types=1);

namespace Beziworld\Cpanel;

use Beziworld\Cpanel\Exceptions\InvalidConfiguration;

/**
 * Validated, immutable settings of a single cPanel or WHM connection.
 */
final readonly class ConnectionConfig
{
    public const string TYPE_WHM = 'whm';

    public const string TYPE_CPANEL = 'cpanel';

    public function __construct(
        public string $name,
        public string $host,
        public string $username,
        #[\SensitiveParameter]
        public string $token,
        public string $type = self::TYPE_WHM,
        public bool|string $verify = true,
        public int $timeout = 60,
        public int $connectTimeout = 10,
    ) {}

    /**
     * @param array<string, mixed> $config Keys: host, username, token, type (whm|cpanel), verify, timeout, connect_timeout.
     *
     * @throws InvalidConfiguration When the host or credentials are missing or the type is invalid.
     */
    public static function fromArray(string $name, array $config): self
    {
        $type = strtolower(self::string($config['type'] ?? null) ?? self::TYPE_WHM);

        if (! in_array($type, [self::TYPE_WHM, self::TYPE_CPANEL], true)) {
            throw InvalidConfiguration::invalidType($name, $type);
        }

        $host = self::string($config['host'] ?? null) ?? throw InvalidConfiguration::missing($name, 'host');

        if (preg_match('#^https?://#i', $host) !== 1) {
            $host = 'https://' . $host;
        }

        $host = rtrim($host, '/');

        if (parse_url($host, PHP_URL_PORT) === null) {
            $host .= $type === self::TYPE_WHM ? ':2087' : ':2083';
        }

        return new self(
            name: $name,
            host: $host,
            username: self::string($config['username'] ?? null) ?? throw InvalidConfiguration::missing($name, 'username'),
            token: self::string($config['token'] ?? null) ?? throw InvalidConfiguration::missing($name, 'token'),
            type: $type,
            verify: self::verify($config['verify'] ?? true),
            timeout: self::positiveInt($config['timeout'] ?? null, 60),
            connectTimeout: self::positiveInt($config['connect_timeout'] ?? null, 10),
        );
    }

    public function isWhm(): bool
    {
        return $this->type === self::TYPE_WHM;
    }

    /**
     * Value of the Authorization header: "whm user:token" or "cpanel user:token".
     */
    public function authorization(): string
    {
        return sprintf('%s %s:%s', $this->type, $this->username, $this->token);
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function verify(mixed $value): bool|string
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '', '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => trim($value),
            };
        }

        return true;
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
