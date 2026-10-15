<?php

declare(strict_types=1);

namespace App\Tests\E2e;

/**
 * Page scripts both journey drivers run. Each is an expression, so Panther runs it after
 * `return`, playwright-php evaluates it as it is, and the Playwright `waitUntil()` wraps it
 * in an arrow function.
 */
final class JourneyScripts
{
    /**
     * Every `id` and `data-test-id` that occurs more than once on the page, outside the debug toolbar.
     */
    public const string REPEATED_IDS = <<<'JS'
        (() => {
            const seen = new Set();
            const repeated = new Set();
            const keys = element => [
                element.id && `#${element.id}`,
                element.dataset.testId && `[data-test-id="${element.dataset.testId}"]`,
            ].filter(Boolean);
            const elements = [...document.querySelectorAll('[id], [data-test-id]')].filter(element => !element.closest('.sf-toolbar'));
            for (const key of elements.flatMap(keys)) {
                (seen.has(key) ? repeated : seen).add(key);
            }
            return [...repeated];
        })()
        JS;

    /**
     * The dev debug toolbar sits over the page; a capture shows the application only.
     */
    public const string HIDE_DEBUG_TOOLBAR = 'document.head.insertAdjacentHTML("beforeend", "<style>.sf-toolbar { display: none !important; }</style>")';

    /**
     * The login Stimulus controller fills the timezone on connect, so a value means the
     * module graph, CSRF controller included, is live.
     */
    public const string LOGIN_FORM_READY = '"" !== (document.querySelector("#login_form_timezone")?.value ?? "")';
    public const string SIGN_IN_SETTLED = '!location.pathname.startsWith("/login")';

    /**
     * Turbo Drive keeps the window between visits; a full page load does not.
     */
    public const string MARK_WINDOW = 'window.journeyMark = true';
    public const string WINDOW_MARKED = 'true === window.journeyMark';

    /**
     * Both health check Mercure boxes show white, the colour before a run. CSP leaves a blocked
     * colour transparent.
     */
    public const string MERCURE_BOXES_WHITE = self::MERCURE_BOX_COLOURS.'.every(colour => "rgb(255, 255, 255)" === colour)';

    /**
     * Both health check Mercure boxes show the colour of the last run.
     */
    public const string MERCURE_BOXES_COLOURED = '(([response, target]) => response === target && !["rgba(0, 0, 0, 0)", "rgb(255, 255, 255)", null].includes(response))('.self::MERCURE_BOX_COLOURS.')';
    private const string MERCURE_BOX_COLOURS = <<<'JS'
        ['health-check-mercure-response', 'health-check-mercure-target']
            .map(testId => document.querySelector(`[data-test-id="${testId}"]`))
            .map(element => element && getComputedStyle(element).backgroundColor)
        JS;

    public static function textContains(string $selector, string $text): string
    {
        return \sprintf(
            '(document.querySelector(%s)?.innerText ?? "").includes(%s)',
            json_encode($selector, \JSON_THROW_ON_ERROR),
            json_encode($text, \JSON_THROW_ON_ERROR),
        );
    }
}
