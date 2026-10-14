<?php

declare(strict_types=1);

namespace App\Tests\E2e;

/**
 * Page scripts both journey drivers run. Each is an expression, so Panther runs it after
 * `return` and playwright-php evaluates it as it is.
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

    public static function textContains(string $selector, string $text): string
    {
        return \sprintf(
            '(document.querySelector(%s)?.innerText ?? "").includes(%s)',
            json_encode($selector, \JSON_THROW_ON_ERROR),
            json_encode($text, \JSON_THROW_ON_ERROR),
        );
    }
}
