<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\AccessAttributeInspector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Fail when an application route declares no #[IsGranted].',
)]
final readonly class SecurityCheckAccessCommand
{
    public const string NAME = 'app:security:check-access';

    public function __construct(
        private RouterInterface $router,
        private AccessAttributeInspector $inspector,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $guards = $this->inspector->inspectRoutes($this->router->getRouteCollection());
        $unguarded = array_keys(array_filter($guards, static fn (bool $guarded): bool => !$guarded));

        if ([] !== $unguarded) {
            $io->table(['Route without #[IsGranted]'], array_map(static fn (string $name): array => [$name], $unguarded));

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d routes declare #[IsGranted].', \count($guards)));

        return Command::SUCCESS;
    }
}
