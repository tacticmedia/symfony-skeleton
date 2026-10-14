<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\DefaultController;
use App\Dto\LoginFormDto;
use App\Form\LoginFormType;
use App\Security\LoginLinkSender;
use App\Security\LoginLinkUrlFactory;
use App\Security\ReturnTarget;
use App\Security\ReturnTargetResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\RateLimiter\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Signature\ExpiredSignatureStorage;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
class LoginController extends AbstractController
{
    public const string ROUTE_LINK = 'login_link';
    public const string ROUTE_LINK_SENT = 'login_link_sent';
    public const string ROUTE_LINK_CHECK = 'login_link_check';

    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/login', name: self::ROUTE_LINK, methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function link(
        Request $request,
        LoginLinkSender $loginLinkSender,
        #[Target('magic_link_ip')]
        RateLimiterFactoryInterface $magicLinkIpLimiter,
        #[Target('magic_link_email')]
        RateLimiterFactoryInterface $magicLinkEmailLimiter,
    ): Response {
        $returnTarget = ReturnTarget::fromQuery($request);
        $returnParameters = $returnTarget?->routeParameters() ?? [];

        $dto = new LoginFormDto();
        $dto->email = $request->hasSession(true) ? $this->authenticationUtils->getLastUsername() : null;

        $form = $this->createForm(LoginFormType::class, $dto, [
            'action' => $this->generateUrl(self::ROUTE_LINK, $returnParameters),
        ]);

        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderLinkRequest($form);
        }

        try {
            $magicLinkIpLimiter->create($request->getClientIp())->consume(1)->ensureAccepted();
            $magicLinkEmailLimiter->create((string) $dto->email)->consume(1)->ensureAccepted();
        } catch (RateLimitExceededException $rateLimitExceededException) {
            return $this->renderLinkRequest($form, $this->rateLimitError($rateLimitExceededException));
        }

        $request->getSession()->set('email', $dto->email);
        $link = $loginLinkSender->send($dto, $returnTarget);

        return null === $link ? $this->redirectToRoute(self::ROUTE_LINK_SENT, $returnParameters) : $this->redirect($link);
    }

    /**
     * render() answers 422 by itself only for an invalid form. A rate-limited form
     * is valid, so Turbo needs the 422 set here.
     *
     * @param FormInterface<LoginFormDto> $form
     */
    private function renderLinkRequest(FormInterface $form, ?TranslatableMessage $rateLimitError = null): Response
    {
        return $this->render('auth/login/request-link.html.twig', [
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
            'rateLimitError' => $rateLimitError,
            'form' => $form,
        ], $rateLimitError instanceof TranslatableMessage ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    private function rateLimitError(RateLimitExceededException $exception): TranslatableMessage
    {
        $retryAfterSeconds = $exception->getRetryAfter()->getTimestamp() - $this->clock->now()->getTimestamp();

        return new TranslatableMessage('login.request.rateLimited_{retry}', [
            'retry' => (int) ceil(max(0, $retryAfterSeconds) / 60),
        ]);
    }

    #[Route(path: '/login/sent', name: self::ROUTE_LINK_SENT, methods: [Request::METHOD_GET])]
    public function sent(Request $request): Response
    {
        $email = $request->getSession()->get('email');
        $request->getSession()->remove('email');

        return $this->render('auth/login/link-sent.html.twig', [
            'email' => $email,
            'returnParameters' => ReturnTarget::fromQuery($request)?->routeParameters() ?? [],
        ]);
    }

    /**
     * @see https://symfony.com/doc/current/security/login_link.html#allow-a-link-to-only-be-used-once
     */
    #[Route(
        path: '/login/confirm',
        name: self::ROUTE_LINK_CHECK,
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function linkConfirm(
        Request $request,
        ReturnTargetResolver $returnTargetResolver,
        #[Autowire(service: 'security.authenticator.expired_login_link_storage.main')]
        ExpiredSignatureStorage $usedLinks,
        #[Autowire(env: 'APP_ENV')]
        string $environment,
        #[Autowire(param: 'app.security.login_link_lifetime')]
        int $loginLinkLifetime,
        #[Autowire(env: 'int:LOGIN_LINK_MAX_USES')]
        int $maxUses,
        #[MapQueryParameter]
        int $expires = 0,
        #[MapQueryParameter(filter: FILTER_VALIDATE_EMAIL, flags: FILTER_NULL_ON_FAILURE)]
        ?string $user = null,
        #[MapQueryParameter]
        ?string $hash = null,
    ): Response {
        $returnTarget = ReturnTarget::fromQuery($request);
        $signedIn = $this->getUser();

        // The browser already is this account, as after a second click on the
        // same email: the link stays unconsumed and the target is reached directly.
        if ($signedIn instanceof UserInterface && $signedIn->getUserIdentifier() === $user) {
            return $this->redirect($returnTargetResolver->url($returnTarget) ?? $this->generateUrl(DefaultController::ROUTE_DEFAULT));
        }

        $errors = $this->linkErrors(
            $expires,
            $user,
            $hash,
            max($loginLinkLifetime, LoginLinkUrlFactory::EMAIL_LIFETIME),
            $usedLinks,
            $maxUses,
        );

        return $this->render('auth/login/check-link.html.twig', [
            'expires' => $expires,
            'user' => $user,
            'hash' => $hash,
            'errors' => $errors,
            'returnParameters' => $returnTarget?->routeParameters() ?? [],
            'autoSubmit' => 'dev' === $environment,
        ]);
    }

    /**
     * An out-of-range expiry never answers with an error: mail scanners open every
     * link with altered values and hold the delivery on a 4xx.
     *
     * @see https://learn.microsoft.com/en-us/microsoft-365/security/office-365-security/set-up-safe-links-policies
     *
     * @return list<TranslatableMessage>
     */
    private function linkErrors(int $expires, ?string $user, ?string $hash, int $maxLifetime, ExpiredSignatureStorage $usedLinks, int $maxUses): array
    {
        $errors = [];
        $now = $this->clock->now()->getTimestamp();

        if ($expires > $now + $maxLifetime) {
            $errors[] = new TranslatableMessage('login.confirm.error.tooFarInTheFuture_{expires}', [
                'expires' => \DateTimeImmutable::createFromTimestamp($expires),
            ]);
        }

        if ($expires < $now) {
            $errors[] = new TranslatableMessage('login.confirm.error.expired');
        }

        if (null === $user) {
            $errors[] = new TranslatableMessage('login.confirm.error.invalidUsername_{user}', [
                'user' => new TranslatableMessage('shared.value.none'),
            ]);
        }

        // Read-only: the hasher counts a use when the POST verifies the link.
        if (null !== $hash && $maxUses > 0 && $usedLinks->countUsages($hash) >= $maxUses) {
            $errors[] = new TranslatableMessage('login.confirm.error.used');
        }

        return $errors;
    }
}
