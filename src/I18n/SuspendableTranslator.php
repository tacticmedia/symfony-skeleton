<?php

declare(strict_types=1);

namespace App\I18n;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renders message ids while the session flag set by
 * {@see \App\Controller\NavigationController::toggleTranslation()} is on, to expose
 * untranslated text.
 */
#[AsDecorator(decorates: 'translator')]
#[WhenNot('test')]
class SuspendableTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly LocaleAwareInterface&TranslatorBagInterface&TranslatorInterface $inner,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<\Stringable|TranslatableInterface|\DateTimeInterface|int|float|string> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof \Symfony\Component\HttpFoundation\Request && $request->hasSession(true) && $request->getSession()->get('translation-disabled')) {
            return $this->fakeTrans($id, $parameters, $domain, $locale);
        }

        return $this->inner->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return $this->inner->getLocale();
    }

    public function setLocale(string $locale): void
    {
        $this->inner->setLocale($locale);
    }

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        return $this->inner->getCatalogue($locale);
    }

    public function getCatalogues(): array
    {
        return $this->inner->getCatalogues();
    }

    /**
     * A date renders in ATOM notation, so a test asserts the value, not a locale's
     * formatting of it.
     *
     * @param array<\Stringable|TranslatableInterface|\DateTimeInterface|int|float|string> $parameters
     */
    protected function fakeTrans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $result = [
            $id,
        ];

        foreach ($parameters as $key => $value) {
            if ($value instanceof TranslatableInterface) {
                $value = $value->trans($this);
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(\DATE_ATOM);
            }

            $result[] = '{'.$key.':'.$value.'}';
        }

        return implode('_', $result);
    }
}
