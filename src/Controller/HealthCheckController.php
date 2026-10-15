<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\MercureAuthorizer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
final class HealthCheckController extends AbstractController
{
    public const string ROUTE_REGULAR = 'health_check_regular';
    public const string ROUTE_FUNCTIONAL = 'health_check_functional';

    #[Route(path: '/health-check', name: self::ROUTE_REGULAR, methods: [Request::METHOD_GET])]
    public function healthCheck(ClockInterface $clock): \Symfony\Component\HttpFoundation\JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'now' => $clock->now()->format('r'),
        ]);
    }

    #[Route(path: '/health-check/functional', name: self::ROUTE_FUNCTIONAL, methods: [Request::METHOD_GET, Request::METHOD_HEAD])]
    public function functionalHealthCheck(
        Request $request,
        MailerInterface $mailer,
        HubInterface $mercureHub,
        MercureAuthorizer $mercureAuthorizer,
        #[MapQueryParameter]
        bool $testMercure = false,
        #[MapQueryParameter]
        int $testMercureCounter = 0,
    ): Response {
        if ($testMercure) {
            $backgroundColor = substr(md5((string) mt_rand()), 0, 6);
            $mercureError = $this->publishMercureUpdate($mercureHub, $backgroundColor, ++$testMercureCounter);
        }

        $emailResult = $request->query->has('email') ? $this->sendTestEmail($mailer) : null;

        $mercureAuthorizer->authorizeOnce('functional_health_check');

        return $this->render('health-check/functional.html.twig', [
            'emailResult' => $emailResult,
            'backgroundColor' => $backgroundColor ?? 'ffffff',
            'mercureError' => $mercureError ?? null,
            'testMercureCounter' => $testMercureCounter,
        ]);
    }

    /**
     * @return string|null why the update was not sent
     */
    private function publishMercureUpdate(HubInterface $mercureHub, string $backgroundColor, int $counter): ?string
    {
        try {
            $data = $this->renderView('_mercure/generic-update.stream.html.twig', [
                'action' => 'replace',
                'target' => 'mercure_target',
                'content' => '<div class="flex items-center justify-center" data-controller="background" data-background-color-value="#'.$backgroundColor.'" id="mercure_target" data-test-id="health-check-mercure-target"><div class="bg-white text-black text-sm rounded-md p-2">Success '.$counter.'</div></div>',
                'rawContent' => true,
            ]);

            $mercureHub->publish(new Update(
                topics: 'functional_health_check',
                data: $data,
                private: true,
            ));

            $this->addFlash('success', new TranslatableMessage('healthCheck.mercure.sent'));
        } catch (\Throwable $throwable) {
            $this->addFlash('error', new TranslatableMessage('healthCheck.mercure.failed_{error}', ['error' => $throwable->getMessage()]));

            return $throwable->getMessage();
        }

        return null;
    }

    /**
     * @return true|string true, or why the email was not sent
     */
    private function sendTestEmail(MailerInterface $mailer): true|string
    {
        try {
            $mailer->send(new TemplatedEmail()
                ->to(new Address($this->getUser()?->getUserIdentifier() ?: 'foo@localhost'))
                ->subject('E-mail test')
                ->htmlTemplate('_emails/test.html.twig')
                ->textTemplate('_emails/test.txt.twig')
                ->context([
                    'lipsum' => false,
                    'user' => $this->getUser(),
                    'text' => 'If you can see this, then the e-mail delivery works.',
                ]));
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }

        return true;
    }
}
