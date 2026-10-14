<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED')]
final class NavigationController extends AbstractController
{
    public const string ROUTE_TOGGLE_TURBO = 'navigation_toggle_turbo';
    public const string ROUTE_TOGGLE_TRANSLATION = 'navigation_toggle_translation';

    /**
     * A path on this origin. A leading "//" or a backslash, tab or newline, which
     * browsers drop, would make it protocol-relative.
     */
    private const string SAME_ORIGIN_PATH = '#^/(?!/)[^\x00-\x20\\\\]*$#';

    #[Route(path: '/navigation/toggle-turbo', name: self::ROUTE_TOGGLE_TURBO, methods: [Request::METHOD_GET])]
    public function toggleTurbo(
        Request $request,
        #[MapQueryParameter(name: '_return_to')] ?string $returnTo = null,
    ): RedirectResponse {
        return $this->toggle($request, 'turbo-disabled', $returnTo);
    }

    #[Route(path: '/navigation/toggle-translation', name: self::ROUTE_TOGGLE_TRANSLATION, methods: [Request::METHOD_GET])]
    public function toggleTranslation(
        Request $request,
        #[MapQueryParameter(name: '_return_to')] ?string $returnTo = null,
    ): RedirectResponse {
        return $this->toggle($request, 'translation-disabled', $returnTo);
    }

    private function toggle(Request $request, string $flag, ?string $returnTo): RedirectResponse
    {
        $session = $request->getSession();

        if ('off' === $request->query->get('force') || $session->has($flag)) {
            $session->remove($flag);
        } else {
            $session->set($flag, true);
        }

        if (null !== $returnTo && 1 === preg_match(self::SAME_ORIGIN_PATH, $returnTo)) {
            return $this->redirect($returnTo);
        }

        return $this->redirectToRoute(DefaultController::ROUTE_DEFAULT);
    }
}
