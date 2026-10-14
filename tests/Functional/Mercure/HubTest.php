<?php

declare(strict_types=1);

namespace App\Tests\Functional\Mercure;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class HubTest extends KernelTestCase
{
    #[TestDox('The Caddy hub accepts a private update signed by the app')]
    public function testHubAcceptsSignedUpdate(): void
    {
        $hub = self::getContainer()->get(HubInterface::class);

        $updateId = $hub->publish(new Update('hub_test', 'data', private: true));

        self::assertStringStartsWith('urn:uuid:', $updateId);
    }
}
