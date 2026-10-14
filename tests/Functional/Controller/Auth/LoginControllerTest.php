<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Auth;

use App\Controller\Auth\LoginController;
use App\Controller\NavigationController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ReturnTarget;
use App\Tests\AppWebTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * The magic-link sign-in at the HTTP layer. The email is read in-process.
 */
final class LoginControllerTest extends AppWebTestCase
{
    use MailerAssertionsTrait;
    private const string PROTECTED_PATH = '/navigation/toggle-turbo';

    #[TestDox('The emailed link signs the user in')]
    public function testMagicLinkEmailSignsInTheUser(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'user@example.com']);

        $confirmCrawler = $client->request('GET', $this->requestLink($client, 'user@example.com'));
        $this->assertResponseIsSuccessful();
        $client->submit($confirmCrawler->filter('[data-test-id="login-confirm"]')->form());

        $this->assertResponseRedirects('/');
        $client->request('GET', self::PROTECTED_PATH);
        $this->assertResponseRedirects('/');
    }

    #[TestDox('A confirmation without a valid hash bounces to the sign-in page')]
    public function testAConfirmPostWithoutATokenBouncesToSignIn(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'user@example.com']);

        $client->request('GET', '/login');
        $client->request('POST', '/login/confirm', [
            'user' => 'user@example.com',
            'expires' => '9999999999',
            'hash' => 'bogus',
        ]);

        $this->assertResponseRedirects('/login');
        $client->request('GET', self::PROTECTED_PATH);
        $this->assertResponseRedirects(self::signInUrl(new ReturnTarget(NavigationController::ROUTE_TOGGLE_TURBO)));
    }

    #[TestDox('An unknown address gets a lowercased account and its first link')]
    public function testUnknownEmailIsAutoProvisionedAndGetsItsFirstLink(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('[data-test-id="login-request"]')->form(['login_form[email]' => 'Nobody@Example.com']));

        $this->assertResponseRedirects('/login/sent');
        $this->assertEmailCount(1);
        self::assertInstanceOf(User::class, self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'nobody@example.com']));
    }

    #[TestDox('An invalid address re-renders the form with 422 and sends nothing')]
    public function testAnInvalidEmailReRendersTheFormWith422(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->filter('[data-test-id="login-request"]')->form(['login_form[email]' => 'not-an-email']));

        $this->assertResponseStatusCodeSame(422);
        $this->assertEmailCount(0);
    }

    #[TestDox('A rate-limited request answers 422 and says when to retry')]
    public function testARateLimitedRequestSaysWhenToRetry(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        // The test environment never throttles, so a real one-per-window limiter stands in.
        self::getContainer()->set('limiter.magic_link_email', new RateLimiterFactory(
            ['id' => 'magic_link_email', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '15 minutes'],
            new InMemoryStorage(),
        ));

        foreach ([302, 422] as $status) {
            $crawler = $client->request('GET', '/login');
            $client->submit($crawler->filter('[data-test-id="login-request"]')->form(['login_form[email]' => 'user@example.com']));
            $this->assertResponseStatusCodeSame($status);
        }

        self::assertSelectorTextContains('[data-test-id="login-rate-limit-error"]', 'login.request.rateLimited_{retry}_{retry:');
    }

    #[TestDox('The detected timezone is stored on the first sign-in only')]
    public function testTheDetectedTimezoneSticksOnFirstSignInOnly(): void
    {
        $client = self::createClient();
        $userRepository = self::getContainer()->get(UserRepository::class);

        foreach (['Australia/Brisbane', 'Europe/Prague'] as $timezone) {
            $crawler = $client->request('GET', '/login');
            $client->submit($crawler->filter('[data-test-id="login-request"]')->form([
                'login_form[email]' => 'traveller@example.com',
                'login_form[timezone]' => $timezone,
            ]));
            $this->assertResponseRedirects('/login/sent');
        }

        self::clearEntityManager();
        self::assertSame('Australia/Brisbane', $userRepository->findOneBy(['email' => 'traveller@example.com'])?->timezone);
    }

    #[TestDox('A known address gets a link and no duplicate account')]
    public function testAKnownEmailGetsALinkWithoutADuplicateAccount(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'taken@example.com', 'displayName' => 'Original']);

        $this->requestLink($client, 'taken@example.com');

        $users = self::getContainer()->get(UserRepository::class)->findBy(['email' => 'taken@example.com']);
        self::assertCount(1, $users);
        self::assertSame('Original', $users[0]->displayName);
    }

    #[TestDox('A protected page visited signed out becomes the target the link carries')]
    public function testAProtectedPageSignedOutBecomesTheSignInTarget(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'user@example.com']);
        $target = new ReturnTarget(NavigationController::ROUTE_TOGGLE_TURBO);

        $client->request('GET', self::PROTECTED_PATH);
        $this->assertResponseRedirects(self::signInUrl($target));

        $loginUrl = $this->requestLink($client, 'user@example.com', $target);
        self::assertEquals($target, self::returnTargetIn($loginUrl));

        $confirmCrawler = $client->request('GET', $loginUrl);
        $client->submit($confirmCrawler->filter('[data-test-id="login-confirm"]')->form());

        $this->assertResponseRedirects(self::PROTECTED_PATH);
    }

    #[TestDox('A second click on a used link sends the signed-in browser straight to the target')]
    public function testTheInterstitialSendsASignedInBrowserStraightToTheTarget(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'user@example.com']);

        $loginUrl = $this->requestLink($client, 'user@example.com', new ReturnTarget(NavigationController::ROUTE_TOGGLE_TURBO));
        $confirmCrawler = $client->request('GET', $loginUrl);
        $client->submit($confirmCrawler->filter('[data-test-id="login-confirm"]')->form());
        $this->assertResponseRedirects(self::PROTECTED_PATH);

        $client->request('GET', $loginUrl);
        $this->assertResponseRedirects(self::PROTECTED_PATH);
    }

    #[TestDox('A used link opened elsewhere offers a new one for the same target')]
    public function testAUsedLinkOffersANewOneForTheSameTarget(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'user@example.com']);
        $target = new ReturnTarget(NavigationController::ROUTE_TOGGLE_TURBO);

        $loginUrl = $this->requestLink($client, 'user@example.com', $target);
        $confirmCrawler = $client->request('GET', $loginUrl);
        $client->submit($confirmCrawler->filter('[data-test-id="login-confirm"]')->form());

        $client->getCookieJar()->clear();
        $crawler = $client->request('GET', $loginUrl);

        $this->assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test-id="login-errors"]', 'login.confirm.error.used');
        self::assertSame(self::signInUrl($target), $crawler->filter('[data-test-id="login-retry"]')->attr('href'));
    }

    #[TestDox('An expired link offers a new one')]
    public function testAnExpiredLinkOffersANewOne(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login/confirm?'.http_build_query(['expires' => time() - 60, 'user' => 'user@example.com', 'hash' => 'x']));

        $this->assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test-id="login-step-title"]', 'login.confirm.expired.heading');
        self::assertSelectorExists('[data-test-id="login-retry"]');
    }

    #[TestDox('A link with a tampered expiry and no user lists both problems')]
    public function testATamperedLinkListsItsProblems(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login/confirm?'.http_build_query(['expires' => time() + 10 * 86400, 'hash' => 'x']));

        $this->assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test-id="login-errors"]', 'login.confirm.error.tooFarInTheFuture_{expires}');
        self::assertSelectorTextContains('[data-test-id="login-errors"]', 'login.confirm.error.invalidUsername_{user}');
    }

    #[TestDox('The sign-in steps render inside the login frame, and the confirmation posts out of it')]
    public function testTheSignInStepsRenderInsideTheLoginFrame(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'user@example.com']);

        $client->request('GET', '/login');

        self::assertSelectorTextContains('[data-test-id="login-frame"] [data-test-id="login-step-title"]', 'login.step.title');
        self::assertSelectorExists('[data-test-id="login-frame"] [data-test-id="login-request"]');

        $confirmCrawler = $client->request('GET', $this->requestLink($client, 'user@example.com'));

        self::assertSelectorTextContains('[data-test-id="login-step-title"]', 'login.confirm.heading');
        self::assertSame('_top', $confirmCrawler->filter('[data-test-id="login-confirm"]')->closest('form')?->attr('data-turbo-frame'));
    }

    #[TestDox('The sent page keeps the return target for Try Again')]
    public function testTheSentPageKeepsTheReturnTarget(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'user@example.com']);
        $target = new ReturnTarget(NavigationController::ROUTE_TOGGLE_TURBO);

        $this->requestLink($client, 'user@example.com', $target);
        $crawler = $client->followRedirect();

        self::assertSame(self::signInUrl($target), $crawler->filter('[data-test-id="login-retry"]')->attr('href'));
    }

    /**
     * Submits the sign-in form, carrying `$target` when given, and returns the link the
     * email carries.
     */
    private function requestLink(KernelBrowser $client, string $email, ?ReturnTarget $target = null): string
    {
        $crawler = $client->request('GET', $target instanceof ReturnTarget ? self::signInUrl($target) : '/login');
        $client->submit($crawler->filter('[data-test-id="login-request"]')->form(['login_form[email]' => $email]));
        $this->assertResponseRedirects($target instanceof ReturnTarget ? self::signInUrl($target, LoginController::ROUTE_LINK_SENT) : '/login/sent');

        $message = $this->getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        $body = $message->getHtmlBody();
        self::assertIsString($body);
        self::assertSame(1, preg_match('#href="(https?://[^"]+/login/confirm[^"]*)"#', $body, $matches));

        return html_entity_decode($matches[1]);
    }
}
