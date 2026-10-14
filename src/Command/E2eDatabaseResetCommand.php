<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * TRUNCATE, never Foundry's drop-and-recreate: dropping force-kills the
 * FrankenPHP workers' connections and wipes the migration bookkeeping the
 * container entrypoint depends on.
 */
#[When('dev')]
#[When('test')]
#[AsCommand(
    name: 'app:e2e:reset-database',
    description: 'Empty the e2e database and load a fixture.',
)]
final readonly class E2eDatabaseResetCommand
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        Application $application,
        #[Argument(description: 'The #[AsFixture] story to load.')]
        ?string $fixture = null,
    ): int {
        $database = (string) $this->connection->getDatabase();

        if (!str_ends_with($database, '_e2e')) {
            $io->error(sprintf('Refusing to reset database "%s": only a *_e2e database is reset. Run it in the php-e2e container.', $database));

            return Command::FAILURE;
        }

        $this->connection->executeStatement(<<<'SQL'
            DO $$ DECLARE row RECORD; BEGIN
                FOR row IN (
                    SELECT tablename FROM pg_tables
                    WHERE schemaname = 'public' AND tablename <> 'doctrine_migration_versions'
                ) LOOP
                    EXECUTE 'TRUNCATE TABLE ' || quote_ident(row.tablename) || ' RESTART IDENTITY CASCADE';
                END LOOP;
            END $$;
            SQL);

        if (null === $fixture) {
            return Command::SUCCESS;
        }

        $load = new ArrayInput([
            'command' => 'foundry:load-fixtures',
            'name' => [$fixture],
            '--append' => true,
        ]);
        $load->setInteractive(false);

        return $application->doRun($load, $output);
    }
}
