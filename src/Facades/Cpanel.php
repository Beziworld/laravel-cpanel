<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Facades;

use Beziworld\Cpanel\CpanelManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beziworld\Cpanel\CpanelClient connection(?string $name = null)
 * @method static \Beziworld\Cpanel\CpanelClient using(\Beziworld\Cpanel\ConnectionConfig|array<string, mixed> $config, string $name = 'runtime')
 * @method static \Beziworld\Cpanel\CpanelClient forUser(string $username)
 * @method static list<string> connectionNames()
 * @method static string defaultConnection()
 * @method static void purge(?string $name = null)
 * @method static \Beziworld\Cpanel\Resources\Accounts accounts()
 * @method static \Beziworld\Cpanel\Resources\Packages packages()
 * @method static \Beziworld\Cpanel\Resources\Sessions sessions()
 * @method static \Beziworld\Cpanel\Resources\Domains domains()
 * @method static \Beziworld\Cpanel\Resources\Dns dns()
 * @method static \Beziworld\Cpanel\Resources\Email email()
 * @method static \Beziworld\Cpanel\Resources\Mysql mysql()
 * @method static \Beziworld\Cpanel\Resources\Ssl ssl()
 * @method static \Beziworld\Cpanel\Resources\Ftp ftp()
 * @method static \Beziworld\Cpanel\Resources\Cron cron()
 * @method static \Beziworld\Cpanel\Resources\Files files()
 * @method static \Beziworld\Cpanel\Resources\Backups backups()
 * @method static \Beziworld\Cpanel\Resources\Stats stats()
 * @method static array<array-key, mixed> whm(string $function, array<string, mixed> $parameters = [], bool $write = false)
 * @method static mixed uapi(string $module, string $function, array<string, mixed> $parameters = [], bool $write = false)
 * @method static array<array-key, mixed> api2(string $module, string $function, array<string, mixed> $parameters = [], bool $write = false)
 *
 * @see CpanelManager
 * @see \Beziworld\Cpanel\CpanelClient
 */
final class Cpanel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CpanelManager::class;
    }
}
