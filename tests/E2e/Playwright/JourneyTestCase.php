<?php

declare(strict_types=1);

namespace App\Tests\E2e\Playwright;

use App\Tests\E2e\JourneyScripts;
use App\Tests\E2e\ResetsE2eDatabase;
use Playwright\Locator\LocatorInterface;
use Playwright\Testing\PlaywrightTestCase;
use TacticMedia\QaBundle\Test\PlaywrightJourneyScreenshots;

/**
 * The Panther journeys ({@see \App\Tests\E2e\Panther\JourneyTestCase}) on playwright-php,
 * against the same php-e2e app. playwright-php starts no web server.
 */
abstract class JourneyTestCase extends PlaywrightTestCase
{
    use PlaywrightJourneyScreenshots {
        captureFullPageScreenshot as private captureScreens;
    }
    use ResetsE2eDatabase;

    /**
     * The Panther journeys write their own root: each producer empties its root once per run.
     */
    protected static function screenshotRoot(): string
    {
        return getcwd().'/var/screenshots/playwright';
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $baseUri = $_SERVER['E2E_BASE_URI'] ?? 'https://localhost';
        \assert(\is_string($baseUri));

        // The library opens a context without options; this one resolves relative URLs.
        $this->context->close();
        $this->context = $this->browser->newContext(['baseURL' => $baseUri]);

        $this->page = $this->context->newPage();
    }

    /**
     * Fails a screen that repeats an `id` or a `data-test-id`, then captures it.
     */
    protected function captureFullPageScreenshot(string $label): void
    {
        $this->page->evaluate(JourneyScripts::HIDE_DEBUG_TOOLBAR);
        $repeated = $this->page->evaluate(JourneyScripts::REPEATED_IDS);

        self::assertSame([], $repeated, \sprintf('The "%s" screen repeats %s.', $label, (string) json_encode($repeated)));

        $this->captureScreens($this->page, $label);
    }

    /**
     * Company-domain addresses skip the inbox in dev: the request redirects to the
     * confirmation, which submits itself.
     */
    protected function signIn(string $email): void
    {
        $this->requestLink($email);
        $this->page->waitForFunction(JourneyScripts::SIGN_IN_SETTLED);
    }

    protected function requestLink(string $email): void
    {
        if (!str_contains($this->page->url(), '/login')) {
            $this->page->goto('/login');
        }

        $this->page->waitForFunction(JourneyScripts::LOGIN_FORM_READY);
        $this->page->locator('#login_form_email')->fill($email);
        $this->click('login-request');
    }

    protected function click(string $testId): void
    {
        $this->testId($testId)->click();
    }

    protected function waitForTestId(string $testId): void
    {
        $this->testId($testId)->waitFor(['state' => 'visible']);
    }

    private function testId(string $testId): LocatorInterface
    {
        return $this->page->locator(\sprintf('[data-test-id="%s"]', $testId));
    }
}
