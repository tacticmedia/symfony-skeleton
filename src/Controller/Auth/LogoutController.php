<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
class LogoutController extends AbstractController
{
    public const string ROUTE_BYE = 'bye';
    public const string ROUTE_LOGOUT = 'logout';

    #[Route('/logout/bye', name: self::ROUTE_BYE)]
    public function bye(): Response
    {
        return $this->render('auth/logout/bye.html.twig');
    }

    #[Route('/logout', name: self::ROUTE_LOGOUT)]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the logout key on the firewall.');
    }
}
