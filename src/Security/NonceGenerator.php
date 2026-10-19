<?php

declare(strict_types=1);

namespace App\Security;

use Nelmio\SecurityBundle\ContentSecurityPolicy\NonceGenerator as ParentNonceGenerator;
use Nelmio\SecurityBundle\ContentSecurityPolicy\NonceGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @see https://github.com/nelmio/NelmioSecurityBundle/issues/321
 */
#[AsDecorator('nelmio_security.nonce_generator')]
final class NonceGenerator implements NonceGeneratorInterface, EventSubscriberInterface
{
    private ?string $requestNonce = null;

    public function __construct(
        #[AutowireDecorated]
        private readonly ParentNonceGenerator $parent,
    ) {
    }

    public function generate(): string
    {
        if ($this->requestNonce) {
            return $this->requestNonce;
        }

        return $this->parent->generate();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 400],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $nonce = $event->getRequest()->headers->get('X-CSP-Nonce');

        // nelmio concatenates the value into the policy unescaped, so
        // "x' 'unsafe-inline" would splice an extra source expression in and
        // disable the XSS mitigation. Accept only the base64 shape the parent
        // generator emits.
        if (null !== $nonce && 1 === preg_match('#^[A-Za-z0-9+/]{16,}={0,2}$#', $nonce)) {
            $this->requestNonce = $nonce;
        }
    }

    public function onKernelResponse(): void
    {
        $this->requestNonce = null;
    }
}
