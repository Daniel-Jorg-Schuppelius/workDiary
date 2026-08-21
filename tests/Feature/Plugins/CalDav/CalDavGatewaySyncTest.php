<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavGatewaySyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\CalDav;

use App\Models\CalDavConnection;
use App\Plugins\CalDav\Services\HttpCalDavGateway;
use Carbon\CarbonImmutable;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\{Request, Response};
use Tests\TestCase;

/**
 * CalDAV-Delta auf der Leitung (Feature 121, MVP-610b): sync-collection wird
 * bevorzugt; kann der Server ihn nicht, greift der Zeitfenster-Report mit
 * ETag-Vergleich. Kein echtes HTTP — Guzzle-MockHandler.
 */
final class CalDavGatewaySyncTest extends TestCase {
    /** @var list<Request> */
    private array $recorded = [];

    /** @param list<Response> $responses */
    private function gateway(array $responses): HttpCalDavGateway {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(function (callable $handler) {
            return function (Request $request, array $options) use ($handler) {
                $this->recorded[] = $request;

                return $handler($request, $options);
            };
        });

        $connection = new CalDavConnection();
        $connection->base_url = 'https://cloud.example.com/remote.php/dav';
        $connection->username = 'svc';
        $connection->app_password = 'secret';
        $connection->calendar_path = 'calendars/team/plan';

        return new HttpCalDavGateway(new GuzzleClient(['handler' => $stack]), $connection);
    }

    private function multiStatus(string $inner): string {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">' . $inner . '</d:multistatus>';
    }

    private function ics(string $uid): string {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:{$uid}\r\nSUMMARY:Termin\r\nDTSTART:20260820T090000Z\r\nDTEND:20260820T100000Z\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    }

    public function test_sync_collection_returns_changes_deletions_and_token(): void {
        $body = $this->multiStatus(
            '<d:response><d:href>/remote.php/dav/calendars/team/plan/a.ics</d:href>'
                . '<d:propstat><d:prop><d:getetag>"e1"</d:getetag><c:calendar-data>' . htmlspecialchars($this->ics('a')) . '</c:calendar-data></d:prop>'
                . '<d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
                . '<d:response><d:href>/remote.php/dav/calendars/team/plan/weg.ics</d:href>'
                . '<d:status>HTTP/1.1 404 Not Found</d:status></d:response>'
                . '<d:sync-token>https://cloud.example.com/ns/sync/42</d:sync-token>'
        );

        $page = $this->gateway([new Response(207, [], $body)])
            ->syncEvents('vorher', [], CarbonImmutable::now(), CarbonImmutable::now()->addDays(30));

        $this->assertCount(1, $page->changed);
        $this->assertSame('a.ics', $page->changed[0]->objectName());
        $this->assertSame('e1', $page->changed[0]->etag);
        $this->assertStringContainsString('UID:a', $page->changed[0]->ics);
        $this->assertSame(['/remote.php/dav/calendars/team/plan/weg.ics'], $page->deleted);
        $this->assertSame('https://cloud.example.com/ns/sync/42', $page->syncToken);

        $request = $this->recorded[0];
        $this->assertSame('REPORT', $request->getMethod());
        $this->assertStringContainsString('<d:sync-token>vorher</d:sync-token>', (string) $request->getBody());
    }

    public function test_server_without_sync_collection_falls_back_to_time_range(): void {
        $query = $this->multiStatus(
            '<d:response><d:href>/remote.php/dav/calendars/team/plan/a.ics</d:href>'
                . '<d:propstat><d:prop><d:getetag>"neu"</d:getetag></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
                . '<d:response><d:href>/remote.php/dav/calendars/team/plan/b.ics</d:href>'
                . '<d:propstat><d:prop><d:getetag>"alt"</d:getetag></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
        );

        $page = $this->gateway([
            new Response(501, [], 'not implemented'),   // sync-collection
            new Response(207, [], $query),              // calendar-query
            new Response(200, [], $this->ics('a')),     // GET des geänderten Objekts
        ])->syncEvents('', ['a.ics' => 'alt', 'b.ics' => 'alt', 'c.ics' => 'alt'], CarbonImmutable::now(), CarbonImmutable::now()->addDays(30));

        // a.ics hat ein neues ETag → geladen; b.ics unverändert → übersprungen;
        // c.ics fehlt im Fenster → gelöscht.
        $this->assertCount(1, $page->changed);
        $this->assertSame('a.ics', $page->changed[0]->objectName());
        $this->assertSame(['c.ics'], $page->deleted);
        $this->assertSame('', $page->syncToken);
        $this->assertSame('GET', $this->recorded[2]->getMethod());
    }

    public function test_transport_failure_yields_an_empty_page(): void {
        $page = $this->gateway([
            new Response(501, [], ''),
            new Response(500, [], ''),
        ])->syncEvents('', [], CarbonImmutable::now(), CarbonImmutable::now()->addDays(30));

        $this->assertSame([], $page->changed);
        $this->assertSame([], $page->deleted);
        $this->assertSame('', $page->syncToken);
    }
}
