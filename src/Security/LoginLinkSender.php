<?php

declare(strict_types=1);

namespace App\Security;

use App\Dto\LoginFormDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provisions the account on the first request, so the outcome never reveals
 * whether an address existed.
 */
final readonly class LoginLinkSender
{
    private const string DEV_SHORTCUT_DOMAIN = '@tacticmedia.com.au';

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private LoginLinkUrlFactory $loginLinkUrlFactory,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Autowire(env: 'APP_ENV')]
        private string $environment,
    ) {
    }

    /**
     * Returns the link only for the dev shortcut; everyone else gets it by email.
     * mail-tester.com addresses test deliverability and get neither an account nor
     * a link.
     */
    public function send(LoginFormDto $data, ?ReturnTarget $returnTarget = null): ?string
    {
        $email = (string) $data->email;

        if (str_ends_with($email, 'mail-tester.com')) {
            return null;
        }

        $user = $this->account($email, $data->timezone);
        $link = $this->loginLinkUrlFactory->createUrl($user, $returnTarget);

        if ($this->devShortcut($email)) {
            return $link;
        }

        $this->mailer->send(
            new TemplatedEmail()
                ->to($user->email)
                ->subject($this->translator->trans('email.loginLink.subject'))
                ->htmlTemplate('_emails/login_link.html.twig')
                ->context(['login_link_url' => $link]),
        );

        return null;
    }

    private function account(string $email, ?string $timezone): User
    {
        $user = $this->userRepository->findOneBy(['email' => $email]) ?? $this->provision($email);

        // First sign-in only: a later sign-in from another place never overwrites it.
        if (null === $user->timezone && null !== $timezone && '' !== $timezone) {
            $user->timezone = $timezone;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function provision(string $email): User
    {
        $user = new User();
        $user->email = $email; // @phpstan-ignore assign.propertyType

        if ($this->devShortcut($email)) {
            $user->roles = ['ROLE_ADMIN'];
        }

        return $user;
    }

    private function devShortcut(string $email): bool
    {
        return 'dev' === $this->environment && str_ends_with($email, self::DEV_SHORTCUT_DOMAIN);
    }
}
