<?php

declare(strict_types=1);

namespace App\Tests\Functional\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A translator sees the notes, not the templates: every unit says where it appears and
 * what each placeholder holds.
 */
final class CatalogueNotesTest extends KernelTestCase
{
    private const string XLIFF = 'urn:oasis:names:tc:xliff:document:2.0';

    /**
     * @return iterable<string, array{string}>
     */
    public static function catalogues(): iterable
    {
        yield 'messages' => ['messages+intl-icu.en.xlf'];
    }

    #[DataProvider('catalogues')]
    #[TestDox('Every unit of the $_dataName catalogue has a context note, and a placeholders note when it takes one')]
    public function testEveryUnitNamesItsContextAndPlaceholders(string $catalogue): void
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::getContainer()->getParameter('kernel.project_dir').'/translations/'.$catalogue));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', self::XLIFF);

        $units = $document->getElementsByTagNameNS(self::XLIFF, 'unit');
        self::assertNotCount(0, $units);

        foreach ($units as $unit) {
            $key = $this->text($xpath, $unit, 'x:segment/x:source');
            self::assertNotSame('', $this->text($xpath, $unit, 'x:notes/x:note[@category="context"]'), $key.' has no context note.');

            if (str_contains($this->text($xpath, $unit, 'x:segment/x:target'), '{')) {
                self::assertNotSame('', $this->text($xpath, $unit, 'x:notes/x:note[@category="placeholders"]'), $key.' has no placeholders note.');
            }
        }
    }

    private function text(\DOMXPath $xpath, \DOMElement $unit, string $path): string
    {
        $text = $xpath->evaluate('string('.$path.')', $unit);
        self::assertIsString($text);

        return $text;
    }
}
