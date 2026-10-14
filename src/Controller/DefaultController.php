<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
class DefaultController extends AbstractController
{
    public const string ROUTE_DEFAULT = 'default';

    #[Route('/', name: self::ROUTE_DEFAULT)]
    public function __invoke(): Response
    {
        return $this->render('default/default.html.twig');
    }
}
