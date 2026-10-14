<?php

declare(strict_types=1);

namespace App\Tests\E2e\Playwright;

use App\Tests\E2e\JourneyScripts;
use App\Tests\Story\AppStory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[Group('playwright')]
final class AuthJourneyE2eTest extends JourneyTestCase
{
    #[TestDox('A visitor is refused an invalid address, gets a link by email, is remembered, signs in with the dev shortcut and signs out')]
    public function testSignInAndOut(): void
    {
        self::loadFixture(AppStory::NAME);

        $this->page->goto('/');
        $this->waitForTestId('default-sign-in');
        $this->captureFullPageScreenshot('Home - Signed Out');

        $this->click('default-sign-in');
        $this->page->waitForFunction(JourneyScripts::LOGIN_FORM_READY);
        $this->captureFullPageScreenshot('Sign In');

        // The browser accepts an address without a dotted domain; the server does not.
        $this->requestLink('someone@localhost');
        $this->page->waitForSelector('#login_form_email[aria-invalid="true"]');
        $this->captureFullPageScreenshot('Sign In - Invalid Address');

        $this->requestLink('visitor@example.com');
        $this->waitForTestId('login-retry');
        $this->captureFullPageScreenshot('Sign In - Link Sent');

        $this->click('login-retry');
        $this->page->locator('#login_form_remember')->check();
        $this->signIn(AppStory::ADMIN_EMAIL);
        $this->waitForTestId('default-signed-in');
        self::assertStringContainsString(AppStory::ADMIN_EMAIL, $this->page->locator('[data-test-id="default-signed-in"]')->innerText());
        $this->captureFullPageScreenshot('Home - Signed In');

        // Remember Me restores the address on this device.
        $this->page->goto('/login');
        $this->page->waitForFunction(JourneyScripts::LOGIN_FORM_READY);
        self::assertSame(AppStory::ADMIN_EMAIL, $this->page->locator('#login_form_email')->inputValue());
        self::assertTrue($this->page->locator('#login_form_remember')->isChecked());

        $this->page->goto('/');
        $this->click('default-sign-out');
        $this->waitForTestId('logout-sign-in');
        $this->captureFullPageScreenshot('Signed Out');

        // Signing out sends Clear-Site-Data, which also forgets the remembered address.
        $this->click('logout-sign-in');
        $this->page->waitForFunction(JourneyScripts::LOGIN_FORM_READY);
        self::assertSame('', $this->page->locator('#login_form_email')->inputValue());
    }

    #[TestDox('An expired link and a tampered link each offer a new one')]
    public function testBrokenLinks(): void
    {
        $this->page->goto('/login/confirm?'.http_build_query(['expires' => time() - 60, 'user' => 'someone@example.com', 'hash' => 'x']));
        $this->waitForTestId('login-retry');
        $this->captureFullPageScreenshot('Sign In - Expired Link');

        $this->page->goto('/login/confirm?'.http_build_query(['expires' => time() + 10 * 86400, 'hash' => 'x']));
        $this->waitForTestId('login-errors');
        $this->captureFullPageScreenshot('Sign In - Invalid Link');

        $this->click('login-retry');
        $this->page->waitForSelector('#login_form_email');
    }
}
