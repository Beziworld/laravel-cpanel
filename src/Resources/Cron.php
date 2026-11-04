<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Resources;

/**
 * Cron jobs of a cPanel account.
 *
 * Neither UAPI nor WHM API 1 provide cron functions, so this resource uses
 * the legacy cPanel API 2 module CronJob.
 */
final class Cron extends Resource
{
    /**
     * Cron jobs: list of ['command' => ..., 'minute' => ..., 'hour' => ..., 'linekey' => ..., ...].
     *
     * @return list<array<array-key, mixed>>
     */
    public function list(): array
    {
        return self::rows($this->connection->api2('CronJob', 'listcron'));
    }

    /**
     * Adds a cron job from a five-field expression, e.g. add('* * * * *', 'php artisan schedule:run').
     */
    public function add(string $expression, string $command): void
    {
        $fields = preg_split('/\s+/', trim($expression));

        if ($fields === false || count($fields) !== 5) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a five-field cron expression.', $expression));
        }

        [$minute, $hour, $day, $month, $weekday] = $fields;

        $this->connection->api2('CronJob', 'add_line', [
            'command' => $command,
            'minute' => $minute,
            'hour' => $hour,
            'day' => $day,
            'month' => $month,
            'weekday' => $weekday,
        ], write: true);
    }

    /**
     * Removes a cron job by the "linekey" returned by list().
     */
    public function remove(string $lineKey): void
    {
        $this->connection->api2('CronJob', 'remove_line', ['linekey' => $lineKey], write: true);
    }
}
