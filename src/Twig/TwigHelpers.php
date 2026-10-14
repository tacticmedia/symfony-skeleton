<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Contracts\Translation\TranslatableInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigTest;

class TwigHelpers
{
    #[AsTwigFilter(name: 'hash')]
    public function hash(string $string, string $algo = 'sha512'): string
    {
        return hash($algo, $string);
    }

    #[AsTwigTest(name: 'translatable')]
    public function isTranslatable(mixed $subject): bool
    {
        return $subject instanceof TranslatableInterface;
    }

    #[AsTwigTest(name: 'stringable')]
    public function isStringable(mixed $subject): bool
    {
        return is_string($subject) || $subject instanceof \Stringable;
    }
}
