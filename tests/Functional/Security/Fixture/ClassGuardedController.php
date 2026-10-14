<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Fixture;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ClassGuardedController
{
    public function index(): void
    {
    }
}
