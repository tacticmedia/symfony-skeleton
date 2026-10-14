<?php

declare(strict_types=1);

namespace App\Tests\Functional\I18n;

use App\I18n\SuspendableTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

#[CoversClass(SuspendableTranslator::class)]
class SuspendableTranslatorTest extends KernelTestCase
{
    #[TestDox('getCatalogue() is delegated')]
    public function testGetCatalogue(): void
    {
        $inner = $this->createMock(Translator::class);
        $inner->expects($this->once())->method('getCatalogue');

        $translator = new SuspendableTranslator(
            $inner,
            $this->createStub(RequestStack::class),
        );

        $translator->getCatalogue();
    }

    #[TestDox('getCatalogues() is delegated')]
    public function testGetCatalogues(): void
    {
        $inner = $this->createMock(Translator::class);
        $inner->expects($this->once())->method('getCatalogues');

        $translator = new SuspendableTranslator(
            $inner,
            $this->createStub(RequestStack::class),
        );

        $translator->getCatalogues();
    }

    #[TestDox('getLocale() is delegated')]
    public function testGetLocale(): void
    {
        $inner = $this->createMock(Translator::class);
        $inner->expects($this->once())->method('getLocale');

        $translator = new SuspendableTranslator(
            $inner,
            $this->createStub(RequestStack::class),
        );

        $translator->getLocale();
    }

    #[TestDox('setLocale() is delegated')]
    public function testSetLocale(): void
    {
        $inner = $this->createMock(Translator::class);
        $inner->expects($this->once())->method('setLocale')->with('en_AU');

        $translator = new SuspendableTranslator(
            $inner,
            $this->createStub(RequestStack::class),
        );

        $translator->setLocale('en_AU');
    }

    #[TestDox('trans() is delegated while translation is on')]
    public function testTrans(): void
    {
        $inner = $this->createMock(Translator::class);
        $inner->expects($this->once())->method('trans')->with('shared.button.submit')->willReturn('translated');

        $translator = new SuspendableTranslator(
            $inner,
            $this->createStub(RequestStack::class),
        );

        self::assertSame('translated', $translator->trans('shared.button.submit'));
    }

    #[TestDox('A TranslatableMessage is delegated while translation is on')]
    public function testTransTranslatableMessage(): void
    {
        $inner = $this->createMock(Translator::class);
        $inner->expects($this->once())->method('trans')->with('shared.button.submit')->willReturn('translated');

        $translator = new SuspendableTranslator(
            $inner,
            $this->createStub(RequestStack::class),
        );

        $translatableMessage = new TranslatableMessage('shared.button.submit');

        $translatableMessage->trans($translator);
    }

    #[TestDox('A suspended session sees the key and its parameters')]
    public function testTransSuspended(): void
    {
        $inner = $this->createMock(Translator::class);
        $inner->expects($this->never())->method('trans'); // The translation method should not be called

        $translator = new SuspendableTranslator(
            $inner,
            $this->getSuspendedTranslationRequestStack(),
        );

        // Will not be translated
        self::assertSame('shared.button.submit', $translator->trans('shared.button.submit'));
    }

    #[TestDox('A suspended session sees the key of a TranslatableMessage')]
    public function testTransSuspendedTranslatableMessage(): void
    {
        $inner = $this->createMock(Translator::class);
        $inner->expects($this->never())->method('trans'); // The translation method should not be called

        $translator = new SuspendableTranslator(
            $inner,
            $this->getSuspendedTranslationRequestStack(),
        );

        $nested1 = new TranslatableMessage('g', ['h' => 'i']);
        $nested2 = new TranslatableMessage('e', ['f' => $nested1]);
        $translatableMessage = new TranslatableMessage('a', ['b' => 'c', 'd' => $nested2]);

        self::assertSame('a_{b:c}_{d:e_{f:g_{h:i}}}', $translatableMessage->trans($translator));
    }

    #[TestDox('A suspended session sees a date parameter in Atom notation')]
    public function testTransSuspendedWritesADateInAtomNotation(): void
    {
        $translator = new SuspendableTranslator(
            $this->createStub(Translator::class),
            $this->getSuspendedTranslationRequestStack(),
        );

        self::assertSame(
            'a_{date:2026-09-16T00:00:00+00:00}',
            $translator->trans('a', ['date' => new \DateTimeImmutable('2026-09-16T00:00:00+00:00')]),
        );
    }

    protected function getSuspendedTranslationRequestStack(): RequestStack
    {
        $session = $this->createStub(SessionInterface::class);
        $session->method('isStarted')->willReturn(true);
        $session->method('get')->willReturn(true);

        $request = $this->createStub(Request::class);
        $request->method('getSession')->willReturn($session);
        $request->method('hasSession')->willReturn(true);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        return $requestStack;
    }
}
