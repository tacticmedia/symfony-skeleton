<?php

declare(strict_types=1);

namespace App\Tests\E2e\Panther;

use App\Tests\E2e\JourneyScripts;
use App\Tests\Story\AppStory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[Group('panther')]
final class HealthCheckJourneyE2eTest extends JourneyTestCase
{
    #[TestDox('A signed-in user receives a private Mercure update on the open page and sends a test email')]
    public function testMercureAndEmail(): void
    {
        self::loadFixture(AppStory::NAME);
        $client = $this->journeyClient();
        $this->signIn($client, AppStory::ADMIN_EMAIL);

        $this->click($client, 'default-health-check');
        // An update published before the EventSource opens is lost.
        $client->waitFor('turbo-mercure-stream-source[connected]');
        $this->captureFullPageScreenshot($client, 'Health Check');

        $this->click($client, 'health-check-mercure-run');
        $this->waitUntil($client, JourneyScripts::textContains('#mercure_target', 'Success 1'), 'The Turbo Stream replaces the target.');
        $this->captureFullPageScreenshot($client, 'Health Check - Mercure Delivered');

        $this->click($client, 'health-check-email-send');
        $client->waitForVisibility('[data-test-id="health-check-email-sent"]');
        $this->captureFullPageScreenshot($client, 'Health Check - Email Sent');
    }
}
