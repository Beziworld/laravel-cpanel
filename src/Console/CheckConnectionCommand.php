<?php

declare(strict_types=1);

namespace Beziworld\Cpanel\Console;

use Beziworld\Cpanel\CpanelManager;
use Beziworld\Cpanel\Exceptions\CpanelException;
use Illuminate\Console\Command;

/**
 * Verifies that a configured cPanel or WHM connection authenticates successfully.
 *
 * WHM connections call the read-only function "version"; cPanel account
 * connections call DomainInfo::list_domains.
 */
final class CheckConnectionCommand extends Command
{
    /** @var string */
    protected $signature = 'cpanel:check {connection? : Connection name from config/cpanel.php}';

    /** @var string */
    protected $description = 'Check the API token of a cPanel or WHM connection';

    public function handle(CpanelManager $manager): int
    {
        $argument = $this->argument('connection');
        $name = is_string($argument) && $argument !== '' ? $argument : $manager->defaultConnection();

        try {
            $client = $manager->connection($name);
            $config = $client->connection()->config();
            $detail = $config->isWhm()
                ? 'cPanel & WHM ' . $client->stats()->version()
                : 'main domain ' . $client->domains()->list()['main'];
        } catch (CpanelException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Connected to %s as %s (%s).', $config->host, $config->username, $config->type));
        $this->components->twoColumnDetail('Server', $detail);

        return self::SUCCESS;
    }
}
