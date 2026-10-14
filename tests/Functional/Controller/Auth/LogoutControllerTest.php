<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Auth;

use App\Tests\AppWebTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;

final class LogoutControllerTest extends AppWebTestCase
{
    #[TestDox('The sign-out link ends the session on the signed-out page')]
    public function testSignOutLandsOnTheByePage(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request('GET', '/');
        $client->request('GET', (string) $crawler->filter('[data-test-id="default-sign-out"]')->attr('href'));

        $this->assertResponseRedirects('/logout/bye');
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test-id="page-title"]', 'logout.heading');
        self::assertSelectorExists('[data-test-id="logout-sign-in"][href="/login"]');

        $client->request('GET', '/');
        self::assertSelectorExists('[data-test-id="default-sign-in"]');
    }
}
