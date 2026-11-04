<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Connection;
use Beziworld\Cpanel\Support\Values;

/**
 * Base class of API areas grouped by topic (accounts, domains, e-mail, ...).
 */
abstract class Resource
{
    public function __construct(
        protected readonly Connection $connection,
    ) {}

    /**
     * Normalises UAPI data that should be a list of records.
     *
     * @return list<array<array-key, mixed>>
     */
    protected static function rows(mixed $data): array
    {
        return Values::rows($data);
    }

    /**
     * Normalises UAPI data that should be an associative array.
     *
     * @return array<array-key, mixed>
     */
    protected static function map(mixed $data): array
    {
        return is_array($data) ? $data : [];
    }
}
