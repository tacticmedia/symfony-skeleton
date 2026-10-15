<?php

declare(strict_types=1);

namespace App\Tests\E2e\Playwright;

use App\Tests\E2e\JourneyScripts;
use App\Tests\Story\AppStory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[Group('playwright')]
final class DevToolsJourneyE2eTest extends JourneyTestCase
{
    #[TestDox('A signed-in developer swaps text for translation keys and turns Turbo Drive off and on')]
    public function testToggles(): void
    {
        self::loadFixture(AppStory::NAME);
        $this->signIn(AppStory::ADMIN_EMAIL);

        $this->click('layout-toggle-translation');
        $this->waitUntil(JourneyScripts::textContains('[data-test-id="page-title"]', 'default.tailwindCheck'));
        $this->captureFullPageScreenshot('Home - Translation Keys');

        $this->click('layout-toggle-translation');
        $this->waitUntil('!'.JourneyScripts::textContains('[data-test-id="page-title"]', 'default.tailwindCheck'));

        // Turbo Drive is on: a link click keeps the window.
        $this->page->evaluate(JourneyScripts::MARK_WINDOW);
        $this->click('default-health-check');
        $this->page->waitForSelector('body[data-test-id="screen-health_check_functional"]');
        self::assertTrue($this->page->evaluate(JourneyScripts::WINDOW_MARKED));

        $this->page->goto('/');
        $this->click('layout-toggle-turbo');
        $this->page->waitForSelector('body[data-turbo="false"]');

        // Turbo Drive is off: a link click loads a new window.
        $this->page->evaluate(JourneyScripts::MARK_WINDOW);
        $this->click('default-health-check');
        $this->page->waitForSelector('body[data-test-id="screen-health_check_functional"]');
        self::assertFalse($this->page->evaluate(JourneyScripts::WINDOW_MARKED));

        $this->page->goto('/');
        $this->click('layout-toggle-turbo');
        $this->page->waitForSelector('body[data-turbo="true"]');
    }
}
