<?php

declare(strict_types=1);

namespace App\Tests\E2e\Playwright;

use App\Tests\E2e\JourneyScripts;
use App\Tests\Story\AppStory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[Group('playwright')]
final class HealthCheckJourneyE2eTest extends JourneyTestCase
{
    #[TestDox('A signed-in user receives a private Mercure update on the open page and sends a test email')]
    public function testMercureAndEmail(): void
    {
        self::loadFixture(AppStory::NAME);
        $this->signIn(AppStory::ADMIN_EMAIL);

        $this->click('default-health-check');
        // An update published before the EventSource opens is lost.
        $this->page->waitForSelector('turbo-mercure-stream-source[connected]', ['state' => 'attached']);
        $this->captureFullPageScreenshot('Health Check');

        $this->click('health-check-mercure-run');
        $this->page->waitForFunction(JourneyScripts::textContains('#mercure_target', 'Success 1'));
        $this->captureFullPageScreenshot('Health Check - Mercure Delivered');

        $this->click('health-check-email-send');
        $this->waitForTestId('health-check-email-sent');
        $this->captureFullPageScreenshot('Health Check - Email Sent');
    }
}
