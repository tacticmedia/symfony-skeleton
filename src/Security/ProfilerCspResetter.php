<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A profiler request disables CSP on the shared handler, and a worker keeps that for every later response.
 */
#[When('dev')]
#[When('test')]
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest')]
final readonly class ProfilerCspResetter
{
    public function __construct(
        #[Autowire(service: 'web_profiler.csp.handler')]
        private ContentSecurityPolicyHandler $handler,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // shortcut: writes a private flag of an @internal class, drop it once Symfony resets the handler itself.
        new \ReflectionProperty(ContentSecurityPolicyHandler::class, 'cspDisabled')->setValue($this->handler, false);
    }
}
