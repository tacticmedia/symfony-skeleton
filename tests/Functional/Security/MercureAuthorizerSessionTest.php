<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Security\MercureAuthorizer;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class MercureAuthorizerSessionTest extends KernelTestCase
{
    #[TestDox('Topics authorised for the session grant a subscriber cookie on every later response')]
    public function testSessionTopicsGrantACookie(): void
    {
        $request = $this->mainRequest();
        $authorizer = self::getContainer()->get(MercureAuthorizer::class);

        $authorizer->authorizeForCurrentSession('a', 'b');
        $authorizer->authorizeForCurrentSession('b', 'c');
        $authorizer->onKernelResponse();

        self::assertSame(['a', 'b', 'c'], $request->getSession()->get('_mercure_topics'));
        self::assertNotEmpty($request->attributes->get('_mercure_authorization_cookies'));
    }

    #[TestDox('Switching user drops the topics authorised for the previous identity')]
    public function testSwitchingUserDropsTheTopics(): void
    {
        $request = $this->mainRequest();
        $authorizer = self::getContainer()->get(MercureAuthorizer::class);

        $authorizer->authorizeForCurrentSession('a');
        $authorizer->authorizeOnce('b');
        $authorizer->onSwitchUser();
        $authorizer->onKernelResponse();

        self::assertFalse($request->getSession()->has('_mercure_topics'));
        self::assertNull($request->attributes->get('_mercure_authorization_cookies'));
    }

    private function mainRequest(): Request
    {
        $request = Request::create('https://localhost/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get(RequestStack::class)->push($request);

        return $request;
    }
}
