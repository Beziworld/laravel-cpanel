<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Support\Values;

/**
 * Usage statistics: per account through UAPI, server-wide through WHM API 1.
 */
final class Stats extends Resource
{
    /**
     * Account statistics from StatsBar::get_stats, keyed by statistic name.
     *
     * @param list<string> $display e.g. ['diskusage', 'bandwidthusage', 'emailaccounts', 'sqldatabases'].
     * @return array<string, array<array-key, mixed>>
     */
    public function account(array $display = ['diskusage', 'bandwidthusage']): array
    {
        $stats = [];

        foreach (self::rows($this->connection->uapi('StatsBar', 'get_stats', ['display' => implode('|', $display)])) as $row) {
            $stats[Values::string($row, 'name', Values::string($row, 'id'))] = $row;
        }

        return $stats;
    }

    /**
     * Disk quota of the account (Quota::get_quota_info).
     *
     * @return array<array-key, mixed>
     */
    public function quota(): array
    {
        return self::map($this->connection->uapi('Quota', 'get_quota_info'));
    }

    /**
     * Resource usage and limits of the account (ResourceUsage::get_usages).
     *
     * @return list<array<array-key, mixed>>
     */
    public function usages(): array
    {
        return self::rows($this->connection->uapi('ResourceUsage', 'get_usages'));
    }

    /**
     * Bandwidth of every account for a month (WHM showbw).
     *
     * @return array<array-key, mixed>
     */
    public function bandwidth(?int $month = null, ?int $year = null, ?string $username = null): array
    {
        return $this->connection->whm('showbw', [
            'month' => $month,
            'year' => $year,
            'search' => $username,
            'searchtype' => $username === null ? null : 'user',
        ]);
    }

    /**
     * Disk usage of every account (WHM get_disk_usage).
     *
     * @return list<array<array-key, mixed>>
     */
    public function diskUsage(): array
    {
        return self::rows($this->connection->whm('get_disk_usage')['accounts'] ?? []);
    }

    /**
     * cPanel and WHM version (WHM version).
     */
    public function version(): string
    {
        return Values::string($this->connection->whm('version'), 'version');
    }
}
