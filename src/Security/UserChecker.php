<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * {@see \Symfony\Component\Security\Http\Firewall\SwitchUserListener} calls
 * checkPostAuth on a switch-in, so a refused user cannot be impersonated.
 * {@see User::isEqualTo()} ends live sessions.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        $this->refuseUnavailable($user);
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        $this->refuseUnavailable($user);
    }

    private function refuseUnavailable(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->closed) {
            throw new CustomUserMessageAccountStatusException('account.closed');
        }

        if (!$user->active) {
            throw new CustomUserMessageAccountStatusException('account.suspended');
        }
    }
}
