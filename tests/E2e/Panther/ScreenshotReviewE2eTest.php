<?php

declare(strict_types=1);

namespace App\Tests\E2e\Panther;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[Group('panther')]
final class ScreenshotReviewE2eTest extends JourneyTestCase
{
    #[TestDox('The screenshot review opens behind the dev firewall')]
    public function testTheReviewOpens(): void
    {
        $client = $this->journeyClient();

        $client->request('GET', '/_dev/screenshots');

        // The bundle markup has no test ids; every review page links to the prompt.
        $client->waitFor('a[href="/_dev/screenshots/prompt"]');
        self::assertSelectorExists('a[href="/_dev/screenshots/prompt"]');
    }
}
