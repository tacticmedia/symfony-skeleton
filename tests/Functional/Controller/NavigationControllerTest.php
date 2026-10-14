<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AppWebTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;

final class NavigationControllerTest extends AppWebTestCase
{
    #[TestDox('The Turbo toggle switches Drive off and on again for the session')]
    public function testTheTurboToggleFlipsTheBodyAttribute(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/navigation/toggle-turbo');
        $this->assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorExists('body[data-turbo="false"]');

        $client->request('GET', '/navigation/toggle-turbo');
        $client->followRedirect();
        self::assertSelectorExists('body[data-turbo="true"]');
    }

    #[TestDox('The translation toggle shows keys until it is forced off')]
    public function testTheTranslationToggleSetsTheSessionFlag(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/navigation/toggle-translation');
        self::assertTrue($client->getRequest()->getSession()->has('translation-disabled'));

        $client->request('GET', '/navigation/toggle-translation?force=off');
        self::assertFalse($client->getRequest()->getSession()->has('translation-disabled'));
    }

    #[TestDox('A same-origin path in _return_to is where the toggle returns to')]
    public function testASameOriginReturnPathIsFollowed(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/navigation/toggle-turbo?_return_to='.urlencode('/health-check/functional?a=1'));

        $this->assertResponseRedirects('/health-check/functional?a=1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignTargets(): iterable
    {
        yield 'absolute URL' => ['https://evil.example/'];
        yield 'protocol-relative' => ['//evil.example/'];
        yield 'backslash' => ['/\\evil.example/'];
        yield 'tab' => ["/\t/evil.example/"];
        yield 'relative' => ['evil'];
    }

    #[DataProvider('foreignTargets')]
    #[TestDox('A _return_to of $_dataName falls back to the home page')]
    public function testAForeignReturnTargetFallsBackToTheHomePage(string $returnTo): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/navigation/toggle-turbo?_return_to='.urlencode($returnTo));

        $this->assertResponseRedirects('/');
    }

    #[TestDox('A signed-out visitor is sent to sign in')]
    public function testTheTogglesNeedASignedInUser(): void
    {
        $client = self::createClient();

        $client->request('GET', '/navigation/toggle-translation');

        $this->assertResponseRedirects();
        self::assertStringStartsWith('/login?', (string) $client->getResponse()->headers->get('Location'));
    }
}
