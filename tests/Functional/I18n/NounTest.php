<?php

declare(strict_types=1);

namespace App\Tests\Functional\I18n;

use App\Entity\User;
use App\I18n\TranslatableNounInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

final class NounTest extends KernelTestCase
{
    #[TestDox('Every entity names itself through TranslatableNounInterface')]
    public function testEveryEntityHasANoun(): void
    {
        $missing = [];
        foreach (self::getContainer()->get(EntityManagerInterface::class)->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (!$metadata->isEmbeddedClass && !$metadata->isMappedSuperclass && !is_a($metadata->getName(), TranslatableNounInterface::class, true)) {
                $missing[] = $metadata->getName();
            }
        }

        self::assertSame([], $missing);
    }

    #[TestDox('A noun is the catalogue unit entity.<class>, a plural of count, reached with count 1 by default')]
    public function testEveryNounIsACountedCatalogueUnit(): void
    {
        $catalogue = self::getContainer()->get('translator')->getCatalogue('en');

        foreach ($this->nouns() as $class) {
            $noun = $class::noun();
            $key = 'entity.'.lcfirst(new \ReflectionClass($class)->getShortName());

            self::assertSame($key, $noun->getMessage(), $class);
            self::assertSame(['count' => 1], $noun->getParameters(), $class);
            self::assertTrue($catalogue->defines($key), $key.' is not in the catalogue.');
            self::assertSame(['count'], IcuArguments::in($catalogue->get($key)), $key);
        }
    }

    #[TestDox('A noun key appears in no source file but its own class, so it always receives its count')]
    public function testANounKeyAppearsOnlyInItsOwnClass(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        $owners = [];
        foreach ($this->nouns() as $class) {
            $owners[$class::noun()->getMessage()] = new \ReflectionClass($class)->getFileName();
        }

        $quoted = '/([\'"])('.implode('|', array_map(static fn (string $key): string => preg_quote($key, '/'), array_keys($owners))).')\1/';

        $strays = [];
        foreach (new Finder()->files()->in(["{$projectDir}/src", "{$projectDir}/templates", "{$projectDir}/assets"])->exclude('vendor') as $file) {
            preg_match_all($quoted, $file->getContents(), $matches);
            $foreign = array_filter(array_unique($matches[2]), static fn (string $key): bool => $owners[$key] !== $file->getRealPath());
            array_push($strays, ...array_map(static fn (string $key): string => "{$key} in {$file->getRelativePathname()}", $foreign));
        }

        self::assertSame([], $strays);
    }

    /**
     * Entities and the embeddables they carry; the metadata factory lists no embeddable.
     *
     * @return list<class-string<TranslatableNounInterface>>
     */
    private function nouns(): array
    {
        $classes = [];
        foreach (self::getContainer()->get(EntityManagerInterface::class)->getMetadataFactory()->getAllMetadata() as $metadata) {
            $classes[] = $metadata->getName();
            foreach ($metadata->embeddedClasses as $embedded) {
                $classes[] = $embedded->class;
            }
        }

        $nouns = [];
        foreach ($classes as $class) {
            if (is_a($class, TranslatableNounInterface::class, true)) {
                $nouns[$class] = $class;
            }
        }

        self::assertArrayHasKey(User::class, $nouns);

        return array_values($nouns);
    }
}
