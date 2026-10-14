<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Fixture;

use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InvokableController
{
    #[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
    public function __invoke(): void
    {
    }
}
