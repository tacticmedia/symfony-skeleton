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
    #[TestDox('A signed-in user sees both boxes coloured under CSP, receives a private Mercure update, and sends a test email')]
    public function testMercureAndEmail(): void
    {
        self::loadFixture(AppStory::NAME);
        $client = $this->journeyClient();
        $this->signIn($client, AppStory::ADMIN_EMAIL);

        $this->click($client, 'default-health-check');
        $client->waitFor('body[data-test-id="screen-health_check_functional"]');
        // Turbo imports a visit from a document without CSP; only a full load enforces it.
        $client->reload();
        $this->waitUntil($client, JourneyScripts::MERCURE_BOXES_WHITE, 'The first render shows both boxes white.');
        // An update published before the EventSource opens is lost.
        $client->waitFor('turbo-mercure-stream-source[connected]');
        $this->captureFullPageScreenshot($client, 'Health Check');

        $this->click($client, 'health-check-mercure-run');
        $this->waitUntil($client, JourneyScripts::textContains('#mercure_target', 'Success 1'), 'The Turbo Stream replaces the target.');
        $this->waitUntil($client, JourneyScripts::MERCURE_BOXES_COLOURED, 'The frame response and the Turbo Stream colour both boxes.');
        $this->captureFullPageScreenshot($client, 'Health Check - Mercure Delivered');

        $this->click($client, 'health-check-email-send');
        $client->waitForVisibility('[data-test-id="health-check-email-sent"]');
        $this->captureFullPageScreenshot($client, 'Health Check - Email Sent');
    }
}
