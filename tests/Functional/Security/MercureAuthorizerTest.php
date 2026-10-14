<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mercure\HubInterface;

final class MercureAuthorizerTest extends WebTestCase
{
    #[TestDox('A response that authorizes no topic clears the subscriber cookie the request carried')]
    public function testClearsSubscriberCookieWithoutTopics(): void
    {
        $client = self::createClient();
        $hub = self::getContainer()->get(HubInterface::class);
        $cookieName = $hub->getCookieName();
        $client->getCookieJar()->set(new Cookie($cookieName, 'stale'));

        $client->request('GET', '/health-check');

        $cookies = $client->getResponse()->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        self::assertTrue($cookies['']['/.well-known/mercure'][$cookieName]->isCleared());
    }
}
