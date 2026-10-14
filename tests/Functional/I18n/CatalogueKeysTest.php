<?php

declare(strict_types=1);

namespace App\Tests\Functional\I18n;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every key follows the naming policy in docs/translations.md: it ends with the
 * placeholders its message takes, and the parts that name code (noun class, entity
 * and property, enum and case, form, email template) exist.
 */
final class CatalogueKeysTest extends KernelTestCase
{
    private const string XLIFF = 'urn:oasis:names:tc:xliff:document:2.0';

    #[TestDox('Every message key follows the naming policy')]
    public function testMessageKeysFollowThePolicy(): void
    {
        self::assertSame([], $this->violations('messages+intl-icu.en.xlf', IcuArguments::in(...), $this->rules()->message(...)));
    }

    /**
     * Framework messages keep their English source as the key and are skipped.
     *
     * @param \Closure(string): list<string>  $placeholders
     * @param \Closure(list<string>): ?string $rule
     *
     * @return array<string, string> violation by key
     */
    private function violations(string $catalogue, \Closure $placeholders, \Closure $rule): array
    {
        $violations = [];
        foreach ($this->units($catalogue) as $key => $target) {
            $violation = ctype_upper($key[0]) ? null : $this->suffixViolation($key, $placeholders($target)) ?? $rule(explode('.', $this->base($key)));
            if (null !== $violation) {
                $violations[$key] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @return array<string, string> target by key
     */
    private function units(string $catalogue): array
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load($this->projectDir().'/translations/'.$catalogue));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', self::XLIFF);

        $units = [];
        foreach ($document->getElementsByTagNameNS(self::XLIFF, 'segment') as $segment) {
            $units[(string) $xpath->evaluate('string(x:source)', $segment)] = (string) $xpath->evaluate('string(x:target)', $segment);
        }

        self::assertNotSame([], $units);

        return $units;
    }

    private function base(string $key): string
    {
        $suffix = strpos($key, '_{');

        return false === $suffix ? $key : substr($key, 0, $suffix);
    }

    /**
     * @param list<string> $placeholders
     */
    private function suffixViolation(string $key, array $placeholders): ?string
    {
        $names = array_values(array_unique($placeholders));
        if (['count'] === $names && CatalogueKeyRules::isNoun($key)) {
            return null;
        }

        sort($names, \SORT_STRING);
        $expected = implode('', array_map(static fn (string $name): string => '_{'.$name.'}', $names));

        return substr($key, \strlen($this->base($key))) === $expected ? null : "the key ends with \"{$expected}\", its placeholders in alphabetical order";
    }

    private function rules(): CatalogueKeyRules
    {
        return new CatalogueKeyRules($this->projectDir());
    }

    private function projectDir(): string
    {
        return self::getContainer()->getParameter('kernel.project_dir');
    }
}
