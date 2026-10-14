<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

final readonly class LoginLinkUrlFactory
{
    /**
     * Links in system emails; the interactive flow uses LOGIN_LINK_LIFETIME, and
     * {@see \App\Controller\Auth\LoginController::linkConfirm()} accepts the longer
     * of the two. Also the firewall's `lifetime`, which sizes the single-use store:
     * a link that outlives its usage record becomes replayable.
     */
    public const int EMAIL_LIFETIME = 86400;

    public function __construct(
        // The firewall-bound handler, not the firewall-aware alias: links are
        // also minted from the CLI, where no Request selects a firewall.
        #[Autowire(service: 'security.authenticator.login_link_handler.main')]
        private LoginLinkHandlerInterface $loginLinkHandler,
        #[Autowire(param: 'app.security.login_link_lifetime')]
        private int $interactiveLifetime,
    ) {
    }

    /**
     * The target is appended after signing; {@see LoginLinkSuccessHandler} resolves
     * it on use.
     */
    public function createUrl(User $user, ?ReturnTarget $returnTarget = null, ?int $lifetime = null): string
    {
        $url = $this->loginLinkHandler
            ->createLoginLink($user, lifetime: $lifetime ?? $this->interactiveLifetime)
            ->getUrl();

        return $returnTarget instanceof ReturnTarget ? $url.'&'.http_build_query($returnTarget->routeParameters()) : $url;
    }
}
