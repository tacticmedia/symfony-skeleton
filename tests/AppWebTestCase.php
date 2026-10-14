<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controller\Auth\LoginController;
use App\Entity\User;
use App\Security\ReturnTarget;
use App\Tests\Factory\UserFactory;
use App\Tests\Story\AppStory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

abstract class AppWebTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected static function clearEntityManager(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
    }

    protected function loginAsAdmin(KernelBrowser $client): User
    {
        $admin = UserFactory::new()->admin()->create(['email' => AppStory::ADMIN_EMAIL]);
        $client->loginUser($admin);

        return $admin;
    }

    /**
     * A sign-in page URL carrying `$target`, as the application generates it.
     */
    protected static function signInUrl(ReturnTarget $target, string $route = LoginController::ROUTE_LINK): string
    {
        return self::getContainer()->get(UrlGeneratorInterface::class)->generate($route, $target->routeParameters());
    }

    /**
     * The `_return` target a URL carries.
     */
    protected static function returnTargetIn(?string $url): ?ReturnTarget
    {
        parse_str((string) parse_url((string) $url, \PHP_URL_QUERY), $query);

        return ReturnTarget::fromParameter($query[ReturnTarget::PARAMETER] ?? null);
    }
}
