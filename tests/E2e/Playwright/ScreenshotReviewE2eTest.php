<?php

declare(strict_types=1);

namespace App\Tests\E2e\Playwright;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

#[Group('e2e')]
#[Group('playwright')]
final class ScreenshotReviewE2eTest extends JourneyTestCase
{
    #[TestDox('The screenshot review opens behind the dev firewall')]
    public function testTheReviewOpens(): void
    {
        $response = $this->page->goto('/_dev/screenshots');

        self::assertSame(200, $response?->status());
        // The bundle markup has no test ids; every review page links to the prompt.
        $this->page->waitForSelector('a[href="/_dev/screenshots/prompt"]');
    }
}
