<?php

declare(strict_types=1);

namespace App\Tests\E2e\Panther;

use App\Tests\E2e\JourneyScripts;
use App\Tests\Story\AppStory;
use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[Group('panther')]
final class AuthJourneyE2eTest extends JourneyTestCase
{
    #[TestDox('A visitor is refused an invalid address, gets a link by email, is remembered, signs in with the dev shortcut and signs out')]
    public function testSignInAndOut(): void
    {
        self::loadFixture(AppStory::NAME);
        $client = $this->journeyClient();

        $client->request('GET', '/');
        $client->waitForVisibility('[data-test-id="default-sign-in"]');
        $this->captureFullPageScreenshot($client, 'Home - Signed Out');

        $this->click($client, 'default-sign-in');
        $client->waitForVisibility('#login_form_email');
        $this->waitUntil($client, JourneyScripts::LOGIN_FORM_READY, 'The login controller fills the timezone.');
        $this->captureFullPageScreenshot($client, 'Sign In');

        // The browser accepts an address without a dotted domain; the server does not.
        $this->requestLink($client, 'someone@localhost');
        $client->waitFor('#login_form_email[aria-invalid="true"]');
        $this->captureFullPageScreenshot($client, 'Sign In - Invalid Address');

        $this->requestLink($client, 'visitor@example.com');
        $client->waitForVisibility('[data-test-id="login-retry"]');
        $this->captureFullPageScreenshot($client, 'Sign In - Link Sent');

        $this->click($client, 'login-retry');
        $client->waitForVisibility('#login_form_remember');
        $client->findElement(WebDriverBy::id('login_form_remember'))->click();
        $this->signIn($client, AppStory::ADMIN_EMAIL);
        $client->waitForVisibility('[data-test-id="default-signed-in"]');
        self::assertStringContainsString(AppStory::ADMIN_EMAIL, $client->findElement(WebDriverBy::cssSelector('[data-test-id="default-signed-in"]'))->getText());
        $this->captureFullPageScreenshot($client, 'Home - Signed In');

        // Remember Me restores the address on this device.
        $client->request('GET', '/login');
        $this->waitUntil($client, JourneyScripts::LOGIN_FORM_READY, 'The login controller fills the timezone.');
        self::assertSame(AppStory::ADMIN_EMAIL, $client->findElement(WebDriverBy::id('login_form_email'))->getAttribute('value'));
        self::assertTrue($client->findElement(WebDriverBy::id('login_form_remember'))->isSelected());

        $client->request('GET', '/');
        $this->click($client, 'default-sign-out');
        $client->waitForVisibility('[data-test-id="logout-sign-in"]');
        $this->captureFullPageScreenshot($client, 'Signed Out');

        // Signing out sends Clear-Site-Data, which also forgets the remembered address.
        $this->click($client, 'logout-sign-in');
        $this->waitUntil($client, JourneyScripts::LOGIN_FORM_READY, 'The login controller fills the timezone.');
        self::assertSame('', $client->findElement(WebDriverBy::id('login_form_email'))->getAttribute('value'));
    }

    #[TestDox('An expired link and a tampered link each offer a new one')]
    public function testBrokenLinks(): void
    {
        $client = $this->journeyClient();

        $client->request('GET', '/login/confirm?'.http_build_query(['expires' => time() - 60, 'user' => 'someone@example.com', 'hash' => 'x']));
        $client->waitForVisibility('[data-test-id="login-retry"]');
        $this->captureFullPageScreenshot($client, 'Sign In - Expired Link');

        $client->request('GET', '/login/confirm?'.http_build_query(['expires' => time() + 10 * 86400, 'hash' => 'x']));
        $client->waitForVisibility('[data-test-id="login-errors"]');
        $this->captureFullPageScreenshot($client, 'Sign In - Invalid Link');

        $this->click($client, 'login-retry');
        $client->waitForVisibility('#login_form_email');
    }
}
