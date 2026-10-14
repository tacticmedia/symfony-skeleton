<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AppWebTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;

final class DefaultControllerTest extends AppWebTestCase
{
    #[TestDox('A visitor sees the sign-in link and no developer tools')]
    public function testAVisitorSeesTheSignInLink(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        self::assertSelectorExists('body[data-test-id="screen-default"]');
        self::assertSelectorExists('[data-test-id="default-sign-in"]');
        self::assertSelectorNotExists('[data-test-id="default-dev-tools"]');
    }

    #[TestDox('A signed-in user sees who they are, the sign-out link and the developer tools')]
    public function testASignedInUserSeesTheDeveloperTools(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['email' => 'user@example.com']));

        $client->request('GET', '/');

        self::assertSelectorTextContains('[data-test-id="default-signed-in"]', 'user@example.com');
        self::assertSelectorExists('[data-test-id="default-sign-out"]');
        self::assertSelectorExists('[data-test-id="layout-toggle-turbo"]');
        self::assertSelectorExists('[data-test-id="layout-toggle-translation"]');
    }
}
