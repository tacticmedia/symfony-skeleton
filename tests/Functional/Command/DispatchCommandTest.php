<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class DispatchCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    #[TestDox('Publishes a Turbo Stream update with an escaped target, an escaped message and the clock time')]
    public function testItPublishesToTheHub(): void
    {
        $clock = self::mockTime('2026-10-15 09:30:00');

        $result = self::runCommand('app:mercure:dispatch', ['topic' => 'dispatch_test', 'target' => 'box"', 'message' => '<b>hi</b>']);

        $this->assertCommandIsSuccessful($result);
        self::assertStringContainsString('Published urn:uuid:', $result->getDisplay());

        $update = self::getContainer()->get(HubInterface::class)->getMessages()[0]['object'] ?? null;
        self::assertInstanceOf(Update::class, $update);
        self::assertStringContainsString('<turbo-stream action="update" targets="#box&quot;">', $update->getData());
        self::assertStringContainsString('&lt;b&gt;hi&lt;/b&gt;', $update->getData());
        self::assertStringContainsString($clock->now()->format('r'), $update->getData());
    }
}
