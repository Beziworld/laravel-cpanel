<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

use Beziworld\Cpanel\Data\DnsRecord;
use Beziworld\Cpanel\Exceptions\ApiException;
use Beziworld\Cpanel\Support\Values;

/**
 * DNS zones: UAPI DNS::parse_zone and DNS::mass_edit_zone for account
 * connections, plus the WHM zone functions for root and reseller tokens.
 *
 * mass_edit_zone requires the current SOA serial of the zone and fails when
 * the zone changed in the meantime; add(), edit() and remove() read the
 * serial first. Failed writes are not retried automatically.
 */
final class Dns extends Resource
{
    /**
     * Resource records of the zone (comments and control lines are skipped).
     *
     * @return list<DnsRecord>
     */
    public function records(string $zone): array
    {
        $records = [];

        foreach (self::rows($this->connection->uapi('DNS', 'parse_zone', ['zone' => $zone])) as $row) {
            $record = DnsRecord::fromParsedZone($row);

            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Current SOA serial number of the zone.
     *
     * @throws ApiException When the zone has no SOA record.
     */
    public function serial(string $zone): int
    {
        foreach ($this->records($zone) as $record) {
            if ($record->type === 'SOA' && isset($record->data[2]) && is_numeric($record->data[2])) {
                return (int) $record->data[2];
            }
        }

        throw ApiException::failed('DNS::parse_zone', ['The zone ' . $zone . ' has no SOA record'], 200, []);
    }

    /**
     * Adds a record and returns the new SOA serial.
     *
     * @param string $name Relative ("www") or absolute ("www.example.com.") name.
     * @param list<string>|string $data Record values, e.g. "192.0.2.1" or ["10", "mail.example.com."] for MX.
     */
    public function add(string $zone, string $name, string $type, array|string $data, int $ttl = 14400): int
    {
        return $this->massEdit($zone, add: [self::record($name, $type, $data, $ttl)]);
    }

    /**
     * Replaces the record that starts at the given line index and returns the new SOA serial.
     *
     * @param list<string>|string $data
     */
    public function edit(string $zone, int $lineIndex, string $name, string $type, array|string $data, int $ttl = 14400): int
    {
        return $this->massEdit($zone, edit: [['line_index' => $lineIndex] + self::record($name, $type, $data, $ttl)]);
    }

    /**
     * Removes the records starting at the given line indexes and returns the new SOA serial.
     */
    public function remove(string $zone, int ...$lineIndexes): int
    {
        return $this->massEdit($zone, remove: array_values($lineIndexes));
    }

    /**
     * Applies several changes in one DNS::mass_edit_zone call and returns the new SOA serial.
     *
     * @param list<array<string, mixed>> $add Items with dname, ttl, record_type and data (list of strings).
     * @param list<array<string, mixed>> $edit Items as in $add plus line_index.
     * @param list<int> $remove Line indexes to remove.
     * @param int|null $serial Current SOA serial; read from the zone when omitted.
     */
    public function massEdit(string $zone, array $add = [], array $edit = [], array $remove = [], ?int $serial = null): int
    {
        $data = self::map($this->connection->uapi('DNS', 'mass_edit_zone', [
            'zone' => $zone,
            'serial' => $serial ?? $this->serial($zone),
            'add' => $add === [] ? null : array_map(self::json(...), $add),
            'edit' => $edit === [] ? null : array_map(self::json(...), $edit),
            'remove' => $remove === [] ? null : $remove,
        ], write: true));

        return Values::int($data, 'new_serial');
    }

    /**
     * Zone records in the WHM format (WHM dumpzone): Line, name, type, ttl and type fields.
     *
     * @return list<array<array-key, mixed>>
     */
    public function dumpZone(string $domain): array
    {
        $zones = self::rows($this->connection->whm('dumpzone', ['domain' => $domain])['zone'] ?? []);

        return self::rows($zones[0]['record'] ?? []);
    }

    /**
     * Adds a record with WHM addzonerecord.
     *
     * @param array<string, scalar> $fields Type fields, e.g. ['address' => '192.0.2.1'], ['cname' => 'x.'],
     *                                      ['exchange' => 'mail.example.com', 'preference' => 10], ['txtdata' => '...'].
     */
    public function addZoneRecord(string $domain, string $name, string $type, array $fields, int $ttl = 14400): void
    {
        $this->connection->whm('addzonerecord', [
            'domain' => $domain,
            'name' => $name,
            'type' => strtoupper($type),
            'class' => 'IN',
            'ttl' => $ttl,
        ] + $fields, write: true);
    }

    /**
     * Updates the record at the given line with WHM editzonerecord.
     *
     * @param array<string, scalar> $fields
     */
    public function editZoneRecord(string $domain, int $line, string $name, string $type, array $fields, int $ttl = 14400): void
    {
        $this->connection->whm('editzonerecord', [
            'domain' => $domain,
            'line' => $line,
            'name' => $name,
            'type' => strtoupper($type),
            'class' => 'IN',
            'ttl' => $ttl,
        ] + $fields, write: true);
    }

    public function removeZoneRecord(string $zone, int $line, ?string $serial = null): void
    {
        $this->connection->whm('removezonerecord', ['zone' => $zone, 'line' => $line, 'serialnum' => $serial], write: true);
    }

    /**
     * @param list<string>|string $data
     * @return array{dname: string, ttl: int, record_type: string, data: list<string>}
     */
    private static function record(string $name, string $type, array|string $data, int $ttl): array
    {
        return [
            'dname' => $name,
            'ttl' => $ttl,
            'record_type' => strtoupper($type),
            'data' => is_array($data) ? $data : [$data],
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function json(array $item): string
    {
        return json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
