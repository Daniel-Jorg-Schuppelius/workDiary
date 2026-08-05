<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphMailTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\{MsgraphMailConnection, Organization, User};
use App\Plugins\Msgraph\Api\MsgraphMailOAuth;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope, Headers};
use Illuminate\Support\Facades\{DB, Mail};
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Graph-Mail-Versand (Feature 102): vierter OAuth-Grant (`Mail.Send`, PKCE,
 * verschlüsselte Tokens), Symfony-Transport `msgraph` (Payload-Konvertierung,
 * X-Header-Durchreichung, Shared-Mailbox-From), mandantenfähige Auflösung
 * über den Org-Routing-Header (Listener stampt aus Mailable-Daten, Transport
 * entfernt ihn vor dem Versand) und Health-Zählung bei Sendefehlern.
 */
final class MsgraphMailTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        config()->set('plugins.msgraph.client_id', 'test-client');
        config()->set('plugins.msgraph.client_secret', 'test-secret');
    }

    /** @param  array<string, mixed>  $tokenResponse */
    private function fakeTokenEndpoint(array $tokenResponse): void {
        $mock = new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], (string) json_encode($tokenResponse)),
        ]);
        $client = new GuzzleClient(['handler' => HandlerStack::create($mock)]);
        app()->instance(MsgraphMailOAuth::class, new MsgraphMailOAuth($client));
    }

    /** @param  array<string, mixed>  $attributes */
    private function connection(array $attributes = []): MsgraphMailConnection {
        return MsgraphMailConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'secret-token-1',
            'status' => MsgraphMailConnection::STATUS_ACTIVE,
        ]);
    }

    public function test_oauth_flow_connects_mail_account_with_mail_send_scope(): void {
        $this->fakeTokenEndpoint([
            'access_token' => 'mail-token-123',
            'refresh_token' => 'mail-refresh-456',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]);
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me' => FakePluginHttp::response([
                'id' => 'user-1', 'displayName' => 'Max Beispiel', 'mail' => 'max@firma.example',
            ]),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.msgraph.mail.oauth.start'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $scope = is_string($query['scope'] ?? null) ? $query['scope'] : '';
        $this->assertStringContainsString('Mail.Send', $scope);
        $this->assertStringContainsString('offline_access', $scope);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';
        $this->assertNotSame('', $state);

        $this->actingAs($this->admin)
            ->get(route('admin.msgraph.mail.oauth.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertRedirect(route('admin.msgraph.index'))
            ->assertSessionHas('success');

        $connection = MsgraphMailConnection::query()->firstOrFail();
        $this->assertSame(MsgraphMailConnection::STATUS_ACTIVE, $connection->status);
        $this->assertSame('mail-token-123', $connection->access_token);
        $this->assertSame('Max Beispiel <max@firma.example>', $connection->account_label);

        // At-rest verschlüsselt + Audit ohne Token-Payload.
        $raw = (string) DB::table('msgraph_mail_connections')->where('id', $connection->id)->value('access_token');
        $this->assertStringNotContainsString('mail-token-123', $raw);
        $this->assertDatabaseHas('audit_logs', ['event' => 'msgraph_mail.connected']);
    }

    public function test_transport_sends_via_graph_send_mail_with_payload(): void {
        $this->connection();
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/sendMail' => FakePluginHttp::response(null, 202),
        ]);

        Mail::mailer('msgraph')->to('kunde@example.test', 'Kunde GmbH')->send(new MsgraphMailTestMail());

        $fake->assertSent(function ($request): bool {
            if (! str_contains((string) $request->getUri(), '/me/sendMail')) {
                return false;
            }
            /** @var array{message: array<string, mixed>, saveToSentItems: bool} $payload */
            $payload = (array) json_decode((string) $request->getBody(), true);
            $message = (array) ($payload['message'] ?? []);
            $to = (array) ($message['toRecipients'] ?? []);
            $attachments = (array) ($message['attachments'] ?? []);
            $headers = (array) ($message['internetMessageHeaders'] ?? []);
            $headerNames = array_column(array_map(static fn ($h): array => (array) $h, $headers), 'name');

            return ($message['subject'] ?? null) === 'Graph-Testmail'
                && (($message['body']['contentType'] ?? null) === 'HTML')
                && str_contains((string) ($message['body']['content'] ?? ''), 'Hallo Graph')
                && (($to[0]['emailAddress']['address'] ?? null) === 'kunde@example.test')
                && (($attachments[0]['contentBytes'] ?? null) === base64_encode('PDF-BYTES'))
                && (($attachments[0]['name'] ?? null) === 'test.pdf')
                && in_array('X-Test-Ref', $headerNames, true)          // Zustellnachweis-Mechanik (M26)
                && ! in_array('X-WorkDiary-Organization', $headerNames, true) // interner Routing-Header bleibt intern
                && ($payload['saveToSentItems'] ?? null) === true;
        });

        $fresh = MsgraphMailConnection::query()->firstOrFail();
        $this->assertNotNull($fresh->last_sent_at);
        $this->assertSame(0, (int) $fresh->consecutive_failures);
    }

    public function test_transport_uses_shared_mailbox_from_address_and_sent_items_flag(): void {
        $this->connection(['from_address' => 'rechnung@firma.example', 'save_to_sent_items' => false]);
        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/sendMail' => FakePluginHttp::response(null, 202),
        ]);

        Mail::mailer('msgraph')->to('kunde@example.test')->send(new MsgraphMailTestMail());

        $fake->assertSent(function ($request): bool {
            /** @var array{message: array<string, mixed>, saveToSentItems: bool} $payload */
            $payload = (array) json_decode((string) $request->getBody(), true);
            $message = (array) ($payload['message'] ?? []);

            return (($message['from']['emailAddress']['address'] ?? null) === 'rechnung@firma.example')
                && ($payload['saveToSentItems'] ?? null) === false;
        });
    }

    public function test_transport_routes_by_organization_header(): void {
        $this->connection(['access_token' => 'secret-token-1']);
        $otherOrg = Organization::factory()->create();
        MsgraphMailConnection::query()->create([
            'organization_id' => $otherOrg->id,
            'access_token' => 'secret-token-2',
            'status' => MsgraphMailConnection::STATUS_ACTIVE,
        ]);

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/sendMail' => FakePluginHttp::response(null, 202),
        ]);

        Mail::mailer('msgraph')->to('kunde@example.test')
            ->send(new MsgraphOrgHeaderTestMail((int) $otherOrg->id));

        $fake->assertSent(fn ($request): bool => $request->getHeaderLine('Authorization') === 'Bearer secret-token-2');
    }

    public function test_transport_fails_when_organization_is_ambiguous(): void {
        $this->connection();
        $otherOrg = Organization::factory()->create();
        MsgraphMailConnection::query()->create([
            'organization_id' => $otherOrg->id,
            'access_token' => 'secret-token-2',
            'status' => MsgraphMailConnection::STATUS_ACTIVE,
        ]);
        FakePluginHttp::fake();

        $this->expectException(TransportExceptionInterface::class);
        Mail::mailer('msgraph')->to('kunde@example.test')->send(new MsgraphMailTestMail());
    }

    public function test_listener_stamps_organization_from_mailable_model(): void {
        config(['mail.default' => 'msgraph']);
        $this->connection(['access_token' => 'secret-token-1']);
        $otherOrg = Organization::factory()->create();
        $reference = MsgraphMailConnection::query()->create([
            'organization_id' => $otherOrg->id,
            'access_token' => 'secret-token-2',
            'status' => MsgraphMailConnection::STATUS_ACTIVE,
        ]);

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/sendMail' => FakePluginHttp::response(null, 202),
        ]);

        // Kein expliziter Header: der Listener findet das org-tragende Modell
        // in den Mailable-Daten und stampt die Organisation von $reference.
        Mail::to('kunde@example.test')->send(new MsgraphOrgStampedTestMail($reference));

        $fake->assertSent(function ($request): bool {
            /** @var array{message: array<string, mixed>} $payload */
            $payload = (array) json_decode((string) $request->getBody(), true);
            $headers = (array) (($payload['message'] ?? [])['internetMessageHeaders'] ?? []);
            $headerNames = array_column(array_map(static fn ($h): array => (array) $h, $headers), 'name');

            return $request->getHeaderLine('Authorization') === 'Bearer secret-token-2'
                && ! in_array('X-WorkDiary-Organization', $headerNames, true);
        });
    }

    public function test_send_failure_records_health_counter_and_throws(): void {
        $connection = $this->connection();
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/sendMail' => FakePluginHttp::response(['error' => ['code' => 'ErrorSendAsDenied']], 403),
        ]);

        try {
            Mail::mailer('msgraph')->to('kunde@example.test')->send(new MsgraphMailTestMail());
            $this->fail('TransportException erwartet.');
        } catch (TransportExceptionInterface) {
            // erwartet
        }

        $fresh = $connection->fresh();
        $this->assertInstanceOf(MsgraphMailConnection::class, $fresh);
        $this->assertSame(1, (int) $fresh->consecutive_failures);
        $this->assertNotNull($fresh->last_error);
    }
}

/** Benannte Test-Mailable (PHPStan-freundlich): HTML-Body, X-Header, PDF-Anhang. */
class MsgraphMailTestMail extends Mailable {
    public function envelope(): Envelope {
        return new Envelope(subject: 'Graph-Testmail');
    }

    public function content(): Content {
        return new Content(htmlString: '<p>Hallo Graph</p>');
    }

    public function headers(): Headers {
        return new Headers(text: ['X-Test-Ref' => 'ref-42']);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array {
        return [Attachment::fromData(static fn (): string => 'PDF-BYTES', 'test.pdf')->withMime('application/pdf')];
    }
}

/** Test-Mailable mit explizitem Org-Routing-Header (A2-Auflösung, Weg 1). */
class MsgraphOrgHeaderTestMail extends Mailable {
    public function __construct(private readonly int $organizationId) {}

    public function envelope(): Envelope {
        return new Envelope(subject: 'Org-Header-Testmail');
    }

    public function content(): Content {
        return new Content(htmlString: '<p>Org-Routing</p>');
    }

    public function headers(): Headers {
        return new Headers(text: [\App\Plugins\Msgraph\Mail\MsgraphMailTransport::HEADER_ORGANIZATION => (string) $this->organizationId]);
    }
}

/** Test-Mailable mit org-tragendem Modell als public Property (A2-Listener-Weg). */
class MsgraphOrgStampedTestMail extends Mailable {
    public function __construct(public MsgraphMailConnection $reference) {}

    public function envelope(): Envelope {
        return new Envelope(subject: 'Org-Stamp-Testmail');
    }

    public function content(): Content {
        return new Content(htmlString: '<p>Org-Stamp</p>');
    }
}
