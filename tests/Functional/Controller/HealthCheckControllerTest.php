<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\AppWebTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mercure\HubInterface;

final class HealthCheckControllerTest extends AppWebTestCase
{
    use ClockSensitiveTrait;
    use MailerAssertionsTrait;

    #[TestDox('The functional health check subscribes to its topic and grants a subscriber cookie for it')]
    public function testFunctionalHealthCheckSubscribes(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health-check/functional');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-mercure-stream-source[src$="/.well-known/mercure?match=functional_health_check"]');

        $hub = self::getContainer()->get(HubInterface::class);
        $cookieName = $hub->getCookieName();
        $cookies = $client->getResponse()->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);
        $token = $cookies['']['/.well-known/mercure'][$cookieName]->getValue();
        $claims = json_decode(sodium_base642bin(explode('.', (string) $token)[1], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame([['match' => 'functional_health_check']], $claims['authorization_details'][0]['topics']);
    }

    #[TestDox('Each functional health check card is labelled by its heading')]
    public function testTheCardsAreLabelledByTheirHeadings(): void
    {
        $client = self::createClient();
        $client->request('GET', '/health-check/functional');

        self::assertSelectorTextSame('section[aria-labelledby="health-check-mercure-heading"] h2#health-check-mercure-heading', 'Mercure Hub');
        self::assertSelectorTextSame('section[aria-labelledby="health-check-email-heading"] h2#health-check-email-heading', 'E-mail delivery');
    }

    #[TestDox('A Mercure run publishes the update, flashes that it was sent and offers the next run')]
    public function testTheMercureRunFlashesSent(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health-check/functional?testMercure=1&testMercureCounter=0');

        self::assertSelectorTextContains('[data-test-id="layout-flashes"]', 'healthCheck.mercure.sent');
        self::assertSelectorTextSame('[data-test-id="health-check-mercure-run"]', 'Run test #2');
    }

    #[TestDox('A signed-in user receives the test email at their address')]
    public function testTheEmailTestMailsTheSignedInUser(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['email' => 'user@example.com']));

        $client->request('GET', '/health-check/functional?email=1');

        self::assertSelectorExists('[data-test-id="health-check-email-sent"]');
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage() ?? self::fail('No email was sent.'), 'To', 'user@example.com');
    }

    #[TestDox('A visitor who is signed out gets no test email')]
    public function testTheEmailTestNeedsASignedInUser(): void
    {
        $client = self::createClient();

        $client->request('GET', '/health-check/functional?email=1');

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(0);
    }

    #[TestDox('The regular health check answers JSON with the clock time')]
    public function testTheRegularHealthCheckAnswersJson(): void
    {
        $clock = self::mockTime('2026-10-15 09:30:00');
        $client = self::createClient();

        $client->request('GET', '/health-check');

        $this->assertResponseIsSuccessful();
        self::assertSame(
            ['status' => 'ok', 'now' => $clock->now()->format('r')],
            json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR),
        );
    }
}
