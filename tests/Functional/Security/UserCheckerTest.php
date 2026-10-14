<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Controller\NavigationController;
use App\Entity\User;
use App\Security\ReturnTarget;
use App\Tests\AppWebTestCase;
use App\Tests\Factory\UserFactory;
use App\Tests\Story\AppStory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * loginUser() bypasses the checker, so sign-in tests drive the real magic-link flow.
 */
final class UserCheckerTest extends AppWebTestCase
{
    private const string PROTECTED_PATH = '/navigation/toggle-turbo';

    #[TestDox('A suspended account cannot sign in with a magic link')]
    public function testSuspendedUserMagicLinkDoesNotSignIn(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'suspended@example.com', 'active' => false]);

        $crawler = $client->request('GET', $this->magicLinkUrlFor($user));
        $client->submit($crawler->filter('[data-test-id="login-confirm"]')->form());

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test-id="login-error"]', 'account.suspended');
        $this->assertSignedOut($client);
    }

    #[TestDox('A closed account cannot sign in with a magic link')]
    public function testClosedUserMagicLinkDoesNotSignIn(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'closed@example.com', 'closedAt' => new \DateTimeImmutable('-1 day')]);

        $crawler = $client->request('GET', $this->magicLinkUrlFor($user));
        $client->submit($crawler->filter('[data-test-id="login-confirm"]')->form());

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test-id="login-error"]', 'account.closed');
        $this->assertSignedOut($client);
    }

    #[TestDox('An admin can impersonate an active account and exit again')]
    public function testImpersonationWorksForActiveUsers(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        UserFactory::createOne(['email' => 'customer@example.com']);

        $client->request('GET', '/?_switch_user=customer@example.com');
        $this->assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test-id="default-signed-in"]', 'customer@example.com');

        $client->request('GET', '/?_switch_user=_exit');
        $client->followRedirect();
        self::assertSelectorTextContains('[data-test-id="default-signed-in"]', AppStory::ADMIN_EMAIL);
    }

    #[TestDox('Impersonating a closed account is refused')]
    public function testImpersonatingAClosedUserIsRefused(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        UserFactory::createOne(['email' => 'closed@example.com', 'closedAt' => new \DateTimeImmutable('-1 day')]);

        $client->request('GET', '/?_switch_user=closed@example.com');

        $this->assertResponseStatusCodeSame(403);
    }

    #[TestDox('Impersonating a suspended account is refused and the admin stays signed in')]
    public function testImpersonatingASuspendedUserIsRefused(): void
    {
        $client = self::createClient();
        $this->loginAsAdmin($client);
        UserFactory::createOne(['email' => 'suspended@example.com', 'active' => false]);

        // SwitchUserListener wraps the checker's refusal in AccessDeniedException.
        $client->request('GET', '/?_switch_user=suspended@example.com');
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/');
        self::assertSelectorTextContains('[data-test-id="default-signed-in"]', AppStory::ADMIN_EMAIL);
    }

    #[TestDox('A live session ends on the next request after a suspension')]
    public function testLiveSessionDiesOnSuspension(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $client->loginUser($user);

        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $managed = $entityManager->find(User::class, $user->id);
        self::assertInstanceOf(User::class, $managed);
        $managed->active = false;
        // DEFERRED_EXPLICIT change tracking: flush() writes only persisted entities.
        $entityManager->persist($managed);
        $entityManager->flush();

        $this->assertSignedOut($client);
    }

    /**
     * The home page is public, so a protected page proves the session is gone.
     */
    private function assertSignedOut(KernelBrowser $client): void
    {
        $client->request('GET', self::PROTECTED_PATH);
        $this->assertResponseRedirects(self::signInUrl(new ReturnTarget(NavigationController::ROUTE_TOGGLE_TURBO)));
    }

    private function magicLinkUrlFor(User $user): string
    {
        /** @var LoginLinkHandlerInterface $loginLinkHandler firewall-specific handler; the interface alias needs an active request */
        $loginLinkHandler = self::getContainer()->get('security.authenticator.login_link_handler.main');

        return $loginLinkHandler->createLoginLink($user)->getUrl();
    }
}
