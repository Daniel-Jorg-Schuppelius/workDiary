<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadClientGatewayTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\Zammad;

use App\Plugins\Support\PluginApiException;
use App\Plugins\Zammad\Services\ZammadClientGateway;
use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{HttpFactory, Response};
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;
use ZammadAPIClient\Core\Auth;
use ZammadAPIClient\Core\Transport\RequestHandler;
use ZammadAPIClient\ZammadClient;

/**
 * Das Gateway gegen das SDK 3.x: Anfragen und Abbildung auf die Arrays des
 * Vertrags, ohne Zammad-Instanz (Guzzle-MockHandler).
 */
final class ZammadClientGatewayTest extends TestCase {
    /** @var list<RequestInterface> */
    private array $requests = [];

    /**
     * @param  list<Response>  $responses
     */
    private function gateway(array $responses): ZammadClientGateway {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(fn (callable $next): callable => function (RequestInterface $request, array $options) use ($next) {
            $this->requests[] = $request;

            return $next($request, $options);
        });
        $handler = new RequestHandler(new Client(['handler' => $stack]), new HttpFactory, 'https://zammad.example', maxRetries: 0, auth: Auth::token('secret'));

        return new ZammadClientGateway(new ZammadClient($handler));
    }

    /**
     * @param  array<int|string, mixed>  $body
     */
    private static function zammadResponse(array $body, int $status = 200): Response {
        return new Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body));
    }

    public function test_list_tickets_requests_an_expanded_page_and_maps_the_contract(): void {
        $gateway = $this->gateway([self::zammadResponse([
            ['id' => 7, 'number' => '31007', 'title' => 'Drucker', 'group_id' => 2, 'state' => 'open', 'customer_id' => 11, 'customer' => 'kunde@example.org', 'organization' => 'Beispiel GmbH'],
            ['id' => 8, 'number' => '31008', 'title' => 'Andere Queue', 'group_id' => 3, 'state' => 'new', 'customer_id' => null],
        ])]);

        $tickets = $gateway->listTickets(2, 3, 50);

        $this->assertSame([[
            'id' => 7,
            'number' => '31007',
            'title' => 'Drucker',
            'group_id' => 2,
            'state' => 'open',
            'customer_id' => 11,
            'customer' => 'kunde@example.org',
            'organization' => 'Beispiel GmbH',
        ]], $tickets);

        $request = $this->requests[0];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/v1/tickets', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(['expand' => 'true', 'page' => '3', 'per_page' => '50'], $query);
        $this->assertSame('Token token=secret', $request->getHeaderLine('Authorization'));
    }

    public function test_probe_turns_an_http_error_into_a_plugin_api_exception_with_status(): void {
        $this->gateway([self::zammadResponse([])])->probe();
        $this->assertCount(1, $this->requests);

        try {
            $this->gateway([self::zammadResponse(['error' => 'Invalid token'], 401)])->probe();
            $this->fail('Ein abgelehnter Token muss die Probe scheitern lassen.');
        } catch (PluginApiException $e) {
            $this->assertSame(401, $e->status);
            $this->assertSame('/tickets', $e->endpoint);
        }
    }

    public function test_update_ticket_state_sends_only_the_changes_with_an_internal_note(): void {
        $gateway = $this->gateway([self::zammadResponse(['id' => 7, 'title' => 'Drucker', 'state' => 'closed'])]);

        $this->assertTrue($gateway->updateTicketState(7, 'closed', 'Erledigt in WorkDiary'));

        $request = $this->requests[0];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame('/api/v1/tickets/7', $request->getUri()->getPath());
        $this->assertSame([
            'state' => 'closed',
            'article' => ['body' => 'Erledigt in WorkDiary', 'type' => 'note', 'internal' => true],
        ], json_decode((string) $request->getBody(), true));
    }

    public function test_update_ticket_state_without_changes_sends_nothing_and_a_missing_ticket_fails(): void {
        $this->assertTrue($this->gateway([])->updateTicketState(7, null, ''));
        $this->assertSame([], $this->requests);

        $this->assertFalse($this->gateway([self::zammadResponse(['error' => 'Not found'], 404)])->updateTicketState(99, 'closed', null));
    }

    public function test_account_time_posts_the_time_unit_and_reports_failures(): void {
        $this->assertTrue($this->gateway([self::zammadResponse(['id' => 1, 'time_unit' => '1.5'], 201)])->accountTime(7, 1.5));

        $request = $this->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/tickets/7/time_accountings', $request->getUri()->getPath());
        $this->assertSame(['ticket_id' => 7, 'time_unit' => '1.5'], json_decode((string) $request->getBody(), true));

        $this->assertFalse($this->gateway([self::zammadResponse(['error' => 'boom'], 500)])->accountTime(7, 1.5));
    }
}
