<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\Auth\LoginController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * A safe request keeps its page as the sign-in target; a POST would replay as a GET.
 */
class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $returnTarget = $request->isMethodSafe() ? ReturnTarget::of($request) : null;

        return new RedirectResponse($this->urlGenerator->generate(
            LoginController::ROUTE_LINK,
            $returnTarget?->routeParameters() ?? [],
        ));
    }
}
