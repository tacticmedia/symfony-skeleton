<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

final class ComponentVariantsTest extends KernelTestCase
{
    use InteractsWithTwigComponents;

    /**
     * @return iterable<string, array{string, array<string, mixed>, list<string>, list<string>}>
     */
    public static function variants(): iterable
    {
        yield 'Button defaults' => ['Button', [], ['px-3', 'bg-brand-yellow-600'], ['w-full']];
        yield 'Button kind, size and full' => ['Button', ['kind' => 'danger', 'size' => 'lg', 'full' => true], ['px-6', 'bg-brand-red-600', 'w-full'], ['px-3', 'bg-brand-yellow-600']];
        yield 'Alert defaults' => ['Alert', [], ['border-gray-200'], []];
        yield 'Alert type' => ['Alert', ['type' => 'error'], ['bg-brand-red-50'], ['border-gray-200']];
        yield 'Badge defaults' => ['Badge', [], ['bg-gray-50'], []];
        yield 'Badge tone' => ['Badge', ['tone' => 'success'], ['bg-green-50'], ['bg-gray-50']];
        yield 'Heading defaults' => ['Heading', [], ['text-base', 'text-gray-900'], []];
        yield 'Heading size and tone' => ['Heading', ['size' => 'xl', 'tone' => 'danger'], ['text-3xl', 'text-brand-red-800'], ['text-base', 'text-gray-900']];
        yield 'Link defaults' => ['Link', ['href' => '/'], ['font-medium'], []];
        yield 'Link kind' => ['Link', ['href' => '/', 'kind' => 'quiet'], ['hover:text-brand-yellow-700'], ['font-medium']];
    }

    /**
     * @param array<string, mixed> $props
     * @param list<string>         $present
     * @param list<string>         $absent
     */
    #[DataProvider('variants')]
    #[TestDox('Props select the variant classes: $_dataName')]
    public function testPropsSelectTheVariantClasses(string $component, array $props, array $present, array $absent): void
    {
        $classes = explode(' ', $this->renderTwigComponent($component, $props)->crawler()->filter('body > *')->attr('class') ?? '');

        foreach ($present as $class) {
            self::assertContains($class, $classes);
        }

        foreach ($absent as $class) {
            self::assertNotContains($class, $classes);
        }
    }
}
