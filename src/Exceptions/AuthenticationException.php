<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Exceptions;

/**
 * Raised when cPanel or WHM rejects the API token.
 *
 * Requests are never retried after this error: repeated failed logins
 * trigger cPHulk brute force protection and may block the client IP.
 */
final class AuthenticationException extends CpanelException
{
    public static function rejected(string $connection, int $status): self
    {
        return new self(sprintf(
            'cPanel rejected the API token of connection "%s" (HTTP %d). Check the username, the token, its ACLs and IP restrictions.',
            $connection,
            $status,
        ));
    }
}
