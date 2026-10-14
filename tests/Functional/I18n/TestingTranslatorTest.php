<?php

declare(strict_types=1);

namespace App\Tests\Functional\I18n;

use App\I18n\TestingTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatableMessage;

#[CoversClass(TestingTranslator::class)]
class TestingTranslatorTest extends KernelTestCase
{
    #[TestDox('A message renders as its key with every parameter, nested messages included')]
    public function testTrans(): void
    {
        $translator = static::getContainer()->get(TestingTranslator::class);

        $nested1 = new TranslatableMessage('g', ['h' => 'i']);
        $nested2 = new TranslatableMessage('e', ['f' => $nested1]);
        $translatableMessage = new TranslatableMessage('a', ['b' => 'c', 'd' => $nested2]);

        self::assertSame('a_{b:c}_{d:e_{f:g_{h:i}}}', $translatableMessage->trans($translator));
    }
}
