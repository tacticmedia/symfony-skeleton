<?php

declare(strict_types=1);

namespace App\Tests\E2e;

use Symfony\Component\Process\Process;

/**
 * Resets app_e2e through {@see \App\Command\E2eDatabaseResetCommand}. The console
 * subprocess inherits the container's DATABASE_NAME, so anywhere but php-e2e the
 * command refuses.
 */
trait ResetsE2eDatabase
{
    /**
     * Reset, then load the named #[AsFixture] story.
     */
    protected static function loadFixture(string $fixture): void
    {
        $process = new Process(
            ['php', 'bin/console', 'app:e2e:reset-database', $fixture, '--no-interaction', '--env=dev'],
            \dirname(__DIR__, 2),
        );
        $process->setTimeout(180);
        $process->mustRun();
    }
}
