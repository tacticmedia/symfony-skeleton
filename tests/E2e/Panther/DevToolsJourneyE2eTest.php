<?php

declare(strict_types=1);

namespace App\Tests\E2e\Panther;

use App\Tests\E2e\JourneyScripts;
use App\Tests\Story\AppStory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[Group('panther')]
final class DevToolsJourneyE2eTest extends JourneyTestCase
{
    #[TestDox('A signed-in developer swaps text for translation keys and turns Turbo Drive off and on')]
    public function testToggles(): void
    {
        self::loadFixture(AppStory::NAME);
        $client = $this->journeyClient();
        $this->signIn($client, AppStory::ADMIN_EMAIL);

        $this->click($client, 'layout-toggle-translation');
        $this->waitUntil($client, JourneyScripts::textContains('[data-test-id="page-title"]', 'default.tailwindCheck'), 'The page shows translation keys.');
        $this->captureFullPageScreenshot($client, 'Home - Translation Keys');

        $this->click($client, 'layout-toggle-translation');
        $this->waitUntil($client, '!'.JourneyScripts::textContains('[data-test-id="page-title"]', 'default.tailwindCheck'), 'The page is translated again.');

        // Turbo Drive is on: a link click keeps the window.
        $client->executeScript(JourneyScripts::MARK_WINDOW);
        $this->click($client, 'default-health-check');
        $client->waitFor('body[data-test-id="screen-health_check_functional"]');
        self::assertTrue($client->executeScript('return '.JourneyScripts::WINDOW_MARKED));

        $client->request('GET', '/');
        $this->click($client, 'layout-toggle-turbo');
        $client->waitFor('body[data-turbo="false"]');

        // Turbo Drive is off: a link click loads a new window.
        $client->executeScript(JourneyScripts::MARK_WINDOW);
        $this->click($client, 'default-health-check');
        $client->waitFor('body[data-test-id="screen-health_check_functional"]');
        self::assertFalse($client->executeScript('return '.JourneyScripts::WINDOW_MARKED));

        $client->request('GET', '/');
        $this->click($client, 'layout-toggle-turbo');
        $client->waitFor('body[data-turbo="true"]');
    }
}
