<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Security\AccessAttributeInspector;
use App\Tests\Functional\Security\Fixture\ClassGuardedController;
use App\Tests\Functional\Security\Fixture\InvokableController;
use App\Tests\Functional\Security\Fixture\MethodGuardedController;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class AccessAttributeInspectorTest extends KernelTestCase
{
    #[TestDox('An application route is guarded by #[IsGranted] on its class or action; a bundle route is not inspected')]
    public function testInspectsApplicationRoutes(): void
    {
        $routes = new RouteCollection();
        $routes->add('class_guarded', new Route('/a', ['_controller' => ClassGuardedController::class.'::index']));
        $routes->add('method_guarded', new Route('/b', ['_controller' => MethodGuardedController::class.'::guarded']));
        $routes->add('method_open', new Route('/c', ['_controller' => MethodGuardedController::class.'::open']));
        $routes->add('invokable', new Route('/d', ['_controller' => InvokableController::class]));
        $routes->add('missing_action', new Route('/e', ['_controller' => MethodGuardedController::class.'::missing']));
        $routes->add('missing_class', new Route('/f', ['_controller' => 'App\Tests\Functional\Security\Fixture\MissingController::index']));
        $routes->add('bundle', new Route('/g', ['_controller' => 'Vendor\Bundle\Controller\ViewerController::index']));

        self::assertSame([
            'class_guarded' => true,
            'method_guarded' => true,
            'method_open' => false,
            'invokable' => true,
            'missing_action' => false,
            'missing_class' => false,
        ], new AccessAttributeInspector()->inspectRoutes($routes));
    }
}
