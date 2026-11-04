<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Exceptions;

/**
 * Raised when an operation is not available for the type of the connection,
 * e.g. a WHM function on a cPanel account connection, or a UAPI call through
 * WHM without a target account.
 */
final class UnsupportedOperation extends CpanelException
{
    public static function requiresWhm(string $function): self
    {
        return new self(sprintf('%s is a WHM API 1 function and needs a connection of type "whm" (root or reseller token).', $function));
    }

    public static function requiresAccount(string $function): self
    {
        return new self(sprintf('%s runs as a cPanel account: call forUser(\'username\') on the WHM client first.', $function));
    }
}
