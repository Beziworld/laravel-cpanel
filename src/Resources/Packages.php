<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Data\HostingPackage;

/**
 * Hosting packages (plans) defined in WHM.
 */
final class Packages extends Resource
{
    /**
     * @return list<HostingPackage>
     */
    public function list(): array
    {
        return array_map(HostingPackage::fromArray(...), self::rows($this->connection->whm('listpkgs')['pkg'] ?? []));
    }

    public function find(string $name): ?HostingPackage
    {
        foreach ($this->list() as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }

        return null;
    }
}
