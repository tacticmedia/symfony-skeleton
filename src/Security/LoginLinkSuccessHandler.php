<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\DefaultController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginLinkSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ReturnTargetResolver $returnTargetResolver,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $returnTarget = ReturnTarget::fromQuery($request);

        if ($request->hasSession()) {
            $returnTarget ??= ReturnTarget::recall($request->getSession());
            ReturnTarget::forget($request->getSession());
        }

        return new RedirectResponse(
            $this->returnTargetResolver->url($returnTarget) ?? $this->urlGenerator->generate(DefaultController::ROUTE_DEFAULT),
        );
    }
}
