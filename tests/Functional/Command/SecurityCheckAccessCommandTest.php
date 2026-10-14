<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\SecurityCheckAccessCommand;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SecurityCheckAccessCommandTest extends KernelTestCase
{
    #[TestDox('Every application route declares #[IsGranted]')]
    public function testTheApplicationPasses(): void
    {
        $result = self::runCommand(SecurityCheckAccessCommand::NAME);

        $this->assertCommandIsSuccessful($result, $result->getDisplay());
    }
}
