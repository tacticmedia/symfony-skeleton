<?php

declare(strict_types=1);

namespace App\Tests\Functional\I18n;

use App\I18n\TranslatableNounInterface;

/**
 * The segment rules of a key, per catalogue ({@see docs/translations.md#naming-keys}). A rule
 * returns the violation, or null.
 */
final readonly class CatalogueKeyRules
{
    private const array FEATURES = ['default', 'healthCheck', 'layout', 'login', 'logout'];
    private const string ENTITY_FIELD_PARTS = 'label|help|placeholder';
    private const string FORM_FIELD_PARTS = '\w+\.(?:label|help|placeholder)|\w+\.choice\.\w+';

    public function __construct(private string $projectDir)
    {
    }

    /**
     * `entity.<entity>` of a class with a noun. Only its noun() reaches the key, always
     * with the count, so the key carries no `_{count}`.
     */
    public static function isNoun(string $key): bool
    {
        return 1 === preg_match('/^entity\.(\w+)$/', $key, $match)
            && is_a('App\\Entity\\'.ucfirst($match[1]), TranslatableNounInterface::class, true);
    }

    /**
     * @param list<string> $segments
     */
    public function message(array $segments): ?string
    {
        return $this->camelCaseViolation($segments) ?? match ($segments[0]) {
            'entity' => self::isNoun(implode('.', $segments)) ? null : $this->entityViolation($segments, self::ENTITY_FIELD_PARTS),
            'enum' => $this->enumViolation($segments),
            'form' => $this->formViolation($segments, self::FORM_FIELD_PARTS),
            'shared' => $this->keyMatches($segments, 'shared\.(?:aria|button|count|dropzone|field|heading|pagination|status|title|value)\.\w+') ? null : 'shared.<kind>.<name> with a listed kind',
            'email' => $this->emailViolation($segments),
            default => $this->screenViolation($segments),
        };
    }

    /**
     * @param list<string> $segments
     */
    private function keyMatches(array $segments, string $pattern): bool
    {
        return 1 === preg_match('/^(?:'.$pattern.')$/', implode('.', $segments));
    }

    /**
     * @param list<string> $segments
     */
    private function camelCaseViolation(array $segments): ?string
    {
        return $this->keyMatches($segments, '[a-z][A-Za-z0-9]*(?:\.[a-z][A-Za-z0-9]*)*') ? null : 'every segment is lower camelCase';
    }

    /**
     * @param list<string> $segments
     */
    private function entityViolation(array $segments, string $parts): ?string
    {
        if (!$this->keyMatches($segments, 'entity\.\w+\.\w+\.(?:'.$parts.')')) {
            return 'entity.<entity>.<property>.<part>';
        }

        $class = 'App\\Entity\\'.ucfirst($segments[1]);

        return property_exists($class, $segments[2]) ? null : "no property {$class}::\${$segments[2]}";
    }

    /**
     * @param list<string> $segments
     */
    private function enumViolation(array $segments): ?string
    {
        if (!$this->keyMatches($segments, 'enum\.\w+\.\w+(?:\.\w+)?')) {
            return 'enum.<enum>.<case>[.<text>]';
        }

        $class = 'App\\Enum\\'.ucfirst($segments[1]);

        return enum_exists($class) && \defined($class.'::'.ucfirst($segments[2])) ? null : "no case {$class}::".ucfirst($segments[2]);
    }

    /**
     * @param list<string> $segments
     */
    private function formViolation(array $segments, string $tail): ?string
    {
        if (!$this->keyMatches($segments, 'form\.\w+\.(?:'.$tail.')')) {
            return 'form.<form>.<field>.<part>, form.<form>.<field>.choice.<name> or, for violations, form.<form>[.<field>].<rule>';
        }

        $name = ucfirst($segments[1]);
        $candidates = ["App\\Form\\{$name}FormType", "App\\Form\\{$name}Type", "App\\Dto\\{$name}Dto", "App\\Dto\\{$name}FormDto"];

        return [] === array_filter($candidates, class_exists(...)) ? "no form type or payload named {$name}" : null;
    }

    /**
     * @param list<string> $segments
     */
    private function emailViolation(array $segments): ?string
    {
        if (!$this->keyMatches($segments, 'email\.\w+\.\w+(?:\.\w+)*')) {
            return 'email.<email>.<element>';
        }

        $file = strtolower((string) preg_replace('/[A-Z]/', '_$0', $segments[1]));
        $directory = $this->projectDir.'/templates/_emails/';
        $found = 'common' === $segments[1] || is_file("{$directory}{$file}.html.twig") || is_file("{$directory}_{$file}.html.twig");

        return $found ? null : "no e-mail template {$file}";
    }

    /**
     * @param list<string> $segments
     */
    private function screenViolation(array $segments): ?string
    {
        if (1 !== preg_match('/^(\w+)\.\w+/', implode('.', $segments), $match)) {
            return '<area>.<element>';
        }

        return \in_array($match[1], self::FEATURES, true) || class_exists('App\\Entity\\'.ucfirst($match[1])) ? null : "unknown area {$match[1]}";
    }
}
