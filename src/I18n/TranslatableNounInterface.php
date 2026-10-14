<?php

declare(strict_types=1);

namespace App\I18n;

use Symfony\Component\Translation\TranslatableMessage;

/**
 * The name the interface gives a class. Each implementer returns a literal
 * `new TranslatableMessage('entity.<class>', ['count' => $count])`: the literal keeps
 * the key visible to translation:extract, and the count is what its ICU plural needs.
 */
interface TranslatableNounInterface
{
    /** 2 selects the nominative plural in every catalogue language: `other` in en, it, de; `few` in cs. */
    public const int PLURAL = 2;

    public static function noun(int $count = 1): TranslatableMessage;
}
