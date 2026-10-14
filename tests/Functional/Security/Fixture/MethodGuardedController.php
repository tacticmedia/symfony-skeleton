<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Fixture;

use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MethodGuardedController
{
    #[IsGranted('ROLE_USER')]
    public function guarded(): void
    {
    }

    public function open(): void
    {
    }
}
