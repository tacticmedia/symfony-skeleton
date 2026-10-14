<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Security\Http\SecurityEvents;

use function Sentry\captureException;

#[AsEventListener(event: SecurityEvents::SWITCH_USER, method: 'onSwitchUser')]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse')]
class MercureAuthorizer
{
    /** @var list<string> */
    private array $topicsAuthorisedOnce = [];

    public function __construct(
        private readonly Authorization $mercureAuthorization,
        private readonly HubInterface $mercureHub,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function authorizeForCurrentSession(string ...$topics): void
    {
        if (!($session = $this->getSession()) instanceof SessionInterface) {
            return;
        }

        $session->set('_mercure_topics', array_values(array_unique([
            ...$session->get('_mercure_topics', []),
            ...$topics,
        ])));
    }

    public function authorizeOnce(string ...$topics): void
    {
        $this->topicsAuthorisedOnce = array_values(array_unique([
            ...$this->topicsAuthorisedOnce,
            ...$topics,
        ]));
    }

    public function onKernelResponse(): void
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request || $this->requestStack->getCurrentRequest() !== $request) {
            return;
        }

        $topics = array_unique(array_filter([
            ...($this->getSession()?->get('_mercure_topics') ?: []),
            ...$this->topicsAuthorisedOnce,
        ]));

        try {
            if ([] !== $topics) {
                $this->mercureAuthorization->setCookie($request, $topics);
            } elseif ($request->cookies->has($this->mercureHub->getCookieName())) {
                $this->mercureAuthorization->clearCookie($request);
            }
        } catch (\Throwable $throwable) {
            // TODO We need to do something better with this. Silencing this error will create a hard-to-debug Mercure problems
            captureException($throwable);
        }
    }

    public function onSwitchUser(): void
    {
        $this->topicsAuthorisedOnce = [];

        if (!($session = $this->getSession()) instanceof SessionInterface) {
            return;
        }

        $session->remove('_mercure_topics');
    }

    /**
     * Get the main request IF it exists and IF the session is started.
     */
    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        return $request instanceof Request && $request->hasSession(true) ? $request->getSession() : null;
    }
}
