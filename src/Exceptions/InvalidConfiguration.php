<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Exceptions;

/**
 * Raised when a connection is not configured or incomplete.
 */
final class InvalidConfiguration extends CpanelException
{
    public static function missing(string $connection, string $key): self
    {
        return new self(sprintf('cPanel connection "%s" has no "%s" configured.', $connection, $key));
    }

    public static function unknownConnection(string $connection): self
    {
        return new self(sprintf('cPanel connection "%s" is not defined in config/cpanel.php.', $connection));
    }

    public static function invalidType(string $connection, string $type): self
    {
        return new self(sprintf('cPanel connection "%s" has an invalid type "%s"; use "whm" or "cpanel".', $connection, $type));
    }
}
