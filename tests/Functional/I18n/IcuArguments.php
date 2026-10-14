<?php

declare(strict_types=1);

namespace App\Tests\Functional\I18n;

/**
 * Names of the arguments an ICU message takes, including those inside plural and select
 * branches. A branch body is message text, so the {Monday} of a select is not an argument.
 */
final class IcuArguments
{
    /**
     * One outermost brace group; group 1 holds what is inside it.
     */
    private const string GROUP = '/\{((?:[^{}]++|\{(?1)\})*+)\}/';

    /**
     * @return list<string>
     */
    public static function in(string $message): array
    {
        // Quoted text is literal: '' is an apostrophe, a quote before a syntax character runs to the next quote.
        $text = (string) preg_replace(["/''/", "/'[{}#|][^']*+'/"], '', $message);
        preg_match_all(self::GROUP, $text, $groups);

        $names = [];
        foreach ($groups[1] as $argument) {
            array_push($names, ...self::ofArgument($argument));
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private static function ofArgument(string $argument): array
    {
        if (1 !== preg_match('/^\s*(\w+)\s*(?:,\s*(\w+)\s*(?:,(.*))?)?$/s', $argument, $parts)) {
            throw new \UnexpectedValueException("Unparsable ICU argument: {{$argument}}");
        }

        $names = [$parts[1]];
        if (\in_array($parts[2] ?? '', ['plural', 'select', 'selectordinal'], true)) {
            preg_match_all(self::GROUP, $parts[3] ?? '', $branches);
            foreach ($branches[1] as $branch) {
                array_push($names, ...self::in($branch));
            }
        }

        return $names;
    }
}
