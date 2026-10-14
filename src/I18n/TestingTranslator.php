<?php

declare(strict_types=1);

namespace App\I18n;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\When;

#[AsDecorator(decorates: 'translator')]
#[When('test')]
class TestingTranslator extends SuspendableTranslator
{
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->fakeTrans($id, $parameters, $domain, $locale);
    }
}
