<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatableMessage;
use Twig\Environment;

final class TwigHelpersTest extends KernelTestCase
{
    #[TestDox('The hash filter defaults to sha512 and takes another algorithm')]
    public function testTheHashFilter(): void
    {
        self::assertSame(hash('sha512', 'a').' '.md5('a'), $this->render("{{ 'a'|hash }} {{ 'a'|hash('md5') }}"));
    }

    #[TestDox('The translatable and stringable tests tell a message, a string and an object apart')]
    public function testTheTypeTests(): void
    {
        $template = '{{ subject is translatable ? "t" : "-" }}{{ subject is stringable ? "s" : "-" }}';

        self::assertSame('t-', $this->render($template, ['subject' => new TranslatableMessage('a')]));
        self::assertSame('-s', $this->render($template, ['subject' => 'a']));
        self::assertSame('--', $this->render($template, ['subject' => new \stdClass()]));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context = []): string
    {
        return self::getContainer()->get(Environment::class)->createTemplate($template)->render($context);
    }
}
