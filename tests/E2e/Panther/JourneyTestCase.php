<?php

declare(strict_types=1);

namespace App\Tests\E2e\Panther;

use App\Tests\E2e\JourneyScripts;
use App\Tests\E2e\ResetsE2eDatabase;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use TacticMedia\QaBundle\Test\JourneyScreenshots;

/**
 * Real-browser journeys against the php-e2e container's app. Navigate by clicking,
 * locate by test id ({@see docs/test-ids.md}), wait on state and never on time.
 */
abstract class JourneyTestCase extends PantherTestCase
{
    use JourneyScreenshots {
        captureFullPageScreenshot as private captureScreens;
    }
    use ResetsE2eDatabase;

    private static bool $appAnswered = false;

    /**
     * The Playwright journeys write their own root: each producer empties its root once per run.
     */
    protected static function screenshotRoot(): string
    {
        return getcwd().'/var/screenshots/panther';
    }

    protected function journeyClient(): Client
    {
        $baseUri = $_SERVER['E2E_BASE_URI'] ?? 'https://localhost';
        \assert(\is_string($baseUri));

        $this->assertAppAnswers($baseUri);

        $client = self::createPantherClient(['external_base_uri' => $baseUri]);

        // Panther reuses one browser per process; every journey starts signed out.
        $client->request('GET', '/login');
        $client->getCookieJar()->clear();

        return $client;
    }

    /**
     * Fails a screen that repeats an `id` or a `data-test-id`, then captures it.
     */
    protected function captureFullPageScreenshot(Client $client, string $label): void
    {
        $client->executeScript(JourneyScripts::HIDE_DEBUG_TOOLBAR);
        $repeated = $client->executeScript('return '.JourneyScripts::REPEATED_IDS);

        self::assertSame([], $repeated, \sprintf('The "%s" screen repeats %s.', $label, (string) json_encode($repeated)));

        $this->captureScreens($client, $label);
    }

    /**
     * Company-domain addresses skip the inbox in dev: the request redirects to the
     * confirmation, which submits itself.
     */
    protected function signIn(Client $client, string $email): void
    {
        $this->requestLink($client, $email);
        $this->waitUntil($client, JourneyScripts::SIGN_IN_SETTLED, \sprintf('Signing in as %s settles off the sign-in pages.', $email));
    }

    protected function requestLink(Client $client, string $email): void
    {
        if (!str_contains($client->getCurrentURL(), '/login')) {
            $client->request('GET', '/login');
        }

        $client->waitForVisibility('#login_form_email');
        $this->waitUntil($client, JourneyScripts::LOGIN_FORM_READY, 'The login controller fills the timezone.');

        $field = $client->findElement(WebDriverBy::id('login_form_email'));
        $field->clear();
        $field->sendKeys($email);
        $this->click($client, 'login-request');
    }

    protected function click(Client $client, string $testId): void
    {
        $selector = \sprintf('[data-test-id="%s"]', $testId);
        $client->waitForVisibility($selector);
        $client->findElement(WebDriverBy::cssSelector($selector))->click();
    }

    protected function waitUntil(Client $client, string $condition, string $expectation, int $seconds = 10): void
    {
        try {
            $client->wait($seconds)->until(static fn (): bool => true === $client->executeScript('return '.$condition));
        } catch (\Exception $exception) {
            self::fail(\sprintf('%s Still on %s: %s', $expectation, $client->getCurrentURL(), $exception->getMessage()));
        }
    }

    /**
     * A FrankenPHP worker that stopped serving still accepts connections, so only a PHP
     * route shows the app answers. Without this check the first navigation spends the
     * whole WebDriver timeout and reads as a browser failure.
     */
    private function assertAppAnswers(string $baseUri): void
    {
        if (self::$appAnswered) {
            return;
        }

        $context = stream_context_create([
            'http' => ['timeout' => 15, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        if (false === @file_get_contents($baseUri.'/health-check', false, $context)) {
            self::fail(\sprintf('The e2e app at %s is not answering. Run `docker compose restart php-e2e`.', $baseUri));
        }

        self::$appAnswered = true;
    }
}
