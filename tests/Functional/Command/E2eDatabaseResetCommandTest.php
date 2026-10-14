<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class E2eDatabaseResetCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[TestDox('Refuses a database whose name does not end in _e2e and leaves its rows alone')]
    public function testRefusesADatabaseOutsideTheE2eSuite(): void
    {
        UserFactory::createOne();

        $result = self::runCommand('app:e2e:reset-database');

        $this->assertCommandFailed($result);
        self::assertStringContainsString('Refusing to reset database', $result->getDisplay());
        self::assertSame(1, UserFactory::count());
    }
}
