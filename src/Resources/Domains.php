<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Support\Values;

/**
 * Main, addon, sub and parked (alias) domains of a cPanel account (UAPI).
 */
final class Domains extends Resource
{
    /**
     * @return array{main: string, addon: list<string>, sub: list<string>, parked: list<string>}
     */
    public function list(): array
    {
        $data = self::map($this->connection->uapi('DomainInfo', 'list_domains'));

        return [
            'main' => Values::string($data, 'main_domain'),
            'addon' => Values::strings($data, 'addon_domains'),
            'sub' => Values::strings($data, 'sub_domains'),
            'parked' => Values::strings($data, 'parked_domains'),
        ];
    }

    /**
     * Detailed data (document roots, IP addresses, types) of every domain.
     *
     * @return array<array-key, mixed>
     */
    public function details(): array
    {
        return self::map($this->connection->uapi('DomainInfo', 'domains_data', ['format' => 'list']));
    }

    /**
     * Creates sub.rootdomain, e.g. addSubdomain('shop', 'example.com').
     *
     * @param string|null $documentRoot Relative to the home directory, e.g. "public_html/shop".
     */
    public function addSubdomain(string $subdomain, string $rootDomain, ?string $documentRoot = null): void
    {
        $this->connection->uapi('SubDomain', 'addsubdomain', [
            'domain' => $subdomain,
            'rootdomain' => $rootDomain,
            'dir' => $documentRoot,
        ], write: true);
    }

    /**
     * @param string $subdomain Full name, e.g. "shop.example.com".
     */
    public function removeSubdomain(string $subdomain): void
    {
        $this->connection->uapi('SubDomain', 'delsubdomain', ['domain' => $subdomain], write: true);
    }

    /**
     * Creates an addon domain with its own document root.
     *
     * @param string $subdomain Internal subdomain label cPanel creates for the addon domain, e.g. "shop".
     */
    public function addAddon(string $domain, string $subdomain, ?string $documentRoot = null): void
    {
        $this->connection->uapi('AddonDomain', 'addaddondomain', [
            'newdomain' => $domain,
            'subdomain' => $subdomain,
            'dir' => $documentRoot,
        ], write: true);
    }

    /**
     * @param string $subdomain Internal subdomain, e.g. "shop_example.com".
     */
    public function removeAddon(string $domain, string $subdomain): void
    {
        $this->connection->uapi('AddonDomain', 'deladdondomain', ['domain' => $domain, 'subdomain' => $subdomain], write: true);
    }

    /**
     * Parks an alias domain on the main domain, or on the given domain.
     */
    public function park(string $domain, ?string $topDomain = null): void
    {
        $this->connection->uapi('Park', 'park', ['domain' => $domain, 'topdomain' => $topDomain], write: true);
    }

    public function unpark(string $domain): void
    {
        $this->connection->uapi('Park', 'unpark', ['domain' => $domain], write: true);
    }
}
