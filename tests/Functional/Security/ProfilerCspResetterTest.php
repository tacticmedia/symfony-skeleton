<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProfilerCspResetterTest extends WebTestCase
{
    #[TestDox('A request in the same kernel keeps the CSP header that an earlier profiler request disabled')]
    public function testTheNextRequestKeepsCsp(): void
    {
        $client = self::createClient();
        $handler = self::getContainer()->get('web_profiler.csp.handler');
        $handler->disableCsp();

        $client->request('GET', '/health-check');

        // The debug toolbar listener does this on every response; the test environment runs no toolbar.
        $response = new Response(headers: ['Content-Security-Policy' => "default-src 'self'"]);
        $handler->updateResponseHeaders(new Request(), $response);
        self::assertTrue($response->headers->has('Content-Security-Policy'));
    }
}
