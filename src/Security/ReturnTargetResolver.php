<?php

declare(strict_types=1);

namespace App\Security;

use App\Controller\Auth\LoginController;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ReturnTargetResolver
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * Null when the router cannot build the target, and for the link check, which
     * would loop the sign-in. A non-strict generator answers an unmet requirement
     * with an empty string.
     */
    public function url(?ReturnTarget $target): ?string
    {
        if (!$target instanceof ReturnTarget || LoginController::ROUTE_LINK_CHECK === $target->route) {
            return null;
        }

        try {
            $url = $this->urlGenerator->generate($target->route, $target->attributes + $target->query);
        } catch (ExceptionInterface) {
            return null;
        }

        return '' === $url ? null : $url;
    }
}
