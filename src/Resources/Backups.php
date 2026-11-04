<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

/**
 * Full account backups (UAPI Backup module).
 */
final class Backups extends Resource
{
    /**
     * Queues a full backup to the home directory; cPanel e-mails $notify when it finishes.
     */
    public function createInHomeDirectory(?string $notify = null): void
    {
        $this->connection->uapi('Backup', 'fullbackup_to_homedir', ['email' => $notify], write: true);
    }

    /**
     * Backup files in the home directory.
     *
     * @return list<array<array-key, mixed>>|array<array-key, mixed>
     */
    public function list(): array
    {
        return self::map($this->connection->uapi('Backup', 'list_backups'));
    }
}
