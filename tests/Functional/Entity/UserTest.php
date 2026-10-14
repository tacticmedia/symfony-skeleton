<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\User;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserTest extends KernelTestCase
{
    #[TestDox('Roles are stored sorted and unique, and plain ROLE_USER is stored as nothing')]
    public function testRolesAreNormalised(): void
    {
        $user = new User();

        self::assertSame(['ROLE_USER'], $user->getRoles());

        $user->roles = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_ADMIN'];
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());

        $user->roles = ['ROLE_USER'];
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    #[TestDox('The email is stored lowercased and is the user identifier')]
    public function testTheEmailIsLowercased(): void
    {
        $user = new User();
        $user->email = 'Someone@Example.com'; // @phpstan-ignore assign.propertyType (the set hook lowercases it)

        self::assertSame('someone@example.com', $user->getUserIdentifier());
    }

    #[TestDox('A closure or a suspension makes the stored user differ from the session user')]
    public function testClosureAndSuspensionEndTheSession(): void
    {
        $session = $this->user();

        self::assertTrue($session->isEqualTo($this->user()));

        $closed = $this->user();
        $closed->closedAt = new \DateTimeImmutable();
        self::assertTrue($closed->closed);
        self::assertFalse($session->isEqualTo($closed));

        $suspended = $this->user();
        $suspended->active = false;
        self::assertFalse($session->isEqualTo($suspended));
    }

    #[TestDox('Each new user gets its own login link signature hash')]
    public function testEachUserGetsASignatureHash(): void
    {
        self::assertNotSame(new User()->loginLinkSignatureHash, new User()->loginLinkSignatureHash);
    }

    private function user(): User
    {
        $user = new User();
        $user->email = 'someone@example.com';

        return $user;
    }
}
