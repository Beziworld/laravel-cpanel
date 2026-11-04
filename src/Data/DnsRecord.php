<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Data;

use Beziworld\Cpanel\Support\Values;

/**
 * Resource record of a DNS zone as returned by the UAPI function DNS::parse_zone.
 *
 * UAPI encodes names and values in base64; they are decoded here. The line
 * index identifies the record for DNS::mass_edit_zone edits and removals.
 */
final readonly class DnsRecord
{
    /**
     * @param list<string> $data Record values, e.g. ["10", "mail.example.com."] for MX.
     */
    public function __construct(
        public int $lineIndex,
        public string $name,
        public string $type,
        public ?int $ttl,
        public array $data,
    ) {}

    /**
     * Returns null for comments and control lines ($TTL, $ORIGIN).
     *
     * @param array<array-key, mixed> $row
     */
    public static function fromParsedZone(array $row): ?self
    {
        if (Values::string($row, 'type') !== 'record') {
            return null;
        }

        $data = [];

        foreach (Values::strings($row, 'data_b64') as $encoded) {
            $data[] = (string) base64_decode($encoded, true);
        }

        return new self(
            lineIndex: Values::int($row, 'line_index'),
            name: (string) base64_decode(Values::string($row, 'dname_b64'), true),
            type: strtoupper(Values::string($row, 'record_type')),
            ttl: Values::nullableInt($row, 'ttl'),
            data: $data,
        );
    }

    /**
     * Record values joined with spaces, e.g. "10 mail.example.com.".
     */
    public function content(): string
    {
        return implode(' ', $this->data);
    }
}
