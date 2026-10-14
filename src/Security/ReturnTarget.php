<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The page to land on after signing in, kept as a route so only
 * {@see ReturnTargetResolver} turns it into a URL. It travels unsigned as the
 * `_return` query parameter ({@see LoginLinkUrlFactory}).
 */
final readonly class ReturnTarget
{
    public const string PARAMETER = '_return';
    private const string SESSION_KEY = '_return_target';

    /**
     * @param array<string, string>   $attributes
     * @param array<array-key, mixed> $query
     */
    public function __construct(
        public string $route,
        public array $attributes = [],
        public array $query = [],
    ) {
    }

    /**
     * The page the request renders.
     */
    public static function of(Request $request): ?self
    {
        return self::fromParameter([
            'route' => $request->attributes->get('_route'),
            'attributes' => $request->attributes->get('_route_params'),
            'query' => $request->query->all(),
        ]);
    }

    public static function fromQuery(Request $request): ?self
    {
        return self::fromParameter($request->query->all()[self::PARAMETER] ?? null);
    }

    public static function recall(SessionInterface $session): ?self
    {
        return self::fromParameter($session->get(self::SESSION_KEY));
    }

    public static function forget(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }

    /**
     * Anything malformed is no target: the value arrives in a URL anyone can edit.
     */
    public static function fromParameter(mixed $value): ?self
    {
        $route = \is_array($value) ? ($value['route'] ?? null) : null;

        if (!\is_array($value) || !\is_string($route) || '' === $route) {
            return null;
        }

        $query = $value['query'] ?? null;

        return new self($route, self::namedScalars($value['attributes'] ?? null), \is_array($query) ? $query : []);
    }

    /**
     * @return array<string, string>
     */
    private static function namedScalars(mixed $values): array
    {
        $scalars = [];

        foreach (\is_array($values) ? $values : [] as $name => $value) {
            if (\is_string($name) && \is_scalar($value)) {
                $scalars[$name] = (string) $value;
            }
        }

        return $scalars;
    }

    /**
     * @param array<array-key, mixed> $query
     */
    public function withQuery(array $query): self
    {
        return new self($this->route, $this->attributes, $query + $this->query);
    }

    public function remember(SessionInterface $session): void
    {
        $session->set(self::SESSION_KEY, $this->toParameter());
    }

    /**
     * @return array{_return: array{route: string, attributes: array<string, string>, query: array<array-key, mixed>}}
     */
    public function routeParameters(): array
    {
        return [self::PARAMETER => $this->toParameter()];
    }

    /**
     * @return array{route: string, attributes: array<string, string>, query: array<array-key, mixed>}
     */
    public function toParameter(): array
    {
        return ['route' => $this->route, 'attributes' => $this->attributes, 'query' => $this->query];
    }
}
