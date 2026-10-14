<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Reads #[IsGranted] where {@see \Symfony\Component\HttpKernel\Event\ControllerEvent::getAttributes()} does: the
 * controller class itself, never a parent, and the action method.
 */
final class AccessAttributeInspector
{
    private const string APPLICATION_NAMESPACE = 'App\\';

    /**
     * @return array<string, bool> application route name => its action declares #[IsGranted]
     */
    public function inspectRoutes(RouteCollection $routes): array
    {
        $guards = [];

        foreach ($routes as $name => $route) {
            $controller = $route->getDefault('_controller');

            if (\is_string($controller) && str_starts_with($controller, self::APPLICATION_NAMESPACE)) {
                [$class, $action] = explode('::', $controller, 2) + [1 => '__invoke'];
                $guards[$name] = $this->declaresAccess($class, $action);
            }
        }

        return $guards;
    }

    private function declaresAccess(string $class, string $action): bool
    {
        if (!class_exists($class) || !method_exists($class, $action)) {
            return false;
        }

        return [] !== new \ReflectionClass($class)->getAttributes(IsGranted::class, \ReflectionAttribute::IS_INSTANCEOF)
            || [] !== new \ReflectionMethod($class, $action)->getAttributes(IsGranted::class, \ReflectionAttribute::IS_INSTANCEOF);
    }
}
