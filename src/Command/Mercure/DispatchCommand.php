<?php

declare(strict_types=1);

namespace App\Command\Mercure;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\UX\Turbo\Helper\TurboStream;

#[AsCommand(
    name: 'app:mercure:dispatch',
    description: 'Dispatch Hello World Turbo Frame to a specified Mercure Topic. Useful for debugging Mercure.',
)]
readonly class DispatchCommand
{
    public function __construct(
        private HubInterface $hub,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Topic name')]
        string $topic,
        #[Argument(description: 'Target element ID')]
        string $target,
        #[Argument(description: 'The text that will be sent')]
        string $message = 'Hello World',
    ): int {
        $result = $this->hub->publish(new Update(
            topics: $topic,
            // shortcut: `targets` is a CSS selector, so an ID that is not a valid CSS identifier breaks it, escape the ID when such IDs appear.
            data: TurboStream::update('#'.$target, implode("\n", [
                '<div style="border:5px solid #'.substr(md5((string) random_int(PHP_INT_MIN, PHP_INT_MAX)), 0, 6).';padding:5px;">',
                '<span id="timestamp" class="block">'.$this->clock->now()->format('r').'</span>',
                '<span id="message">'.htmlspecialchars($message, ENT_QUOTES | ENT_HTML5).'</span>',
                '</div>',
            ])),
            private: false,
        ));

        $io->success('Published '.$result);

        return Command::SUCCESS;
    }
}
