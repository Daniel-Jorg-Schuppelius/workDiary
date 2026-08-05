<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GraphMailboxGatewayTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Mail;

use App\Models\{EmailConnection, MsgraphMailConnection, User};
use App\Services\Mail\{GraphMailboxGateway, MailboxGateway, TransportSelectingMailboxGateway};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Mail-Eingang über Microsoft Graph (Feature 102, MS365-Plan B):
 * GraphMailboxGateway als Drop-in hinter dem MailboxGateway-Interface —
 * ungelesene Nachrichten abrufen (HTML→Text, Threading-Header, Anhänge,
 * Graph-ID als externalId), Verarbeitung = gelesen + optional verschieben,
 * Transport-Weiche imap/msgraph, Admin-Formular-Leitplanken.
 */
final class GraphMailboxGatewayTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('plugins.msgraph.client_id', 'cid');
        config()->set('plugins.msgraph.client_secret', 'sec');
    }

    private function graphMailConnection(): MsgraphMailConnection {
        return MsgraphMailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'mail-token',
            'status' => MsgraphMailConnection::STATUS_ACTIVE,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function mailbox(array $attributes = []): EmailConnection {
        return EmailConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'M365-Postfach',
            'transport' => EmailConnection::TRANSPORT_MSGRAPH,
            'folder' => 'INBOX',
            'active' => true,
        ]);
    }

    public function test_fetch_maps_graph_messages_to_parsed_messages(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox();

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages*' => FakePluginHttp::response([
                'value' => [[
                    'id' => 'graph-id-1',
                    'internetMessageId' => '<msg-1@firma.example>',
                    'subject' => 'Rechnung 4711',
                    'from' => ['emailAddress' => ['address' => 'Kunde@Acme.test', 'name' => 'Acme GmbH']],
                    'receivedDateTime' => '2026-08-05T09:00:00Z',
                    'body' => ['contentType' => 'html', 'content' => '<p>Bitte <b>prüfen</b>.</p>'],
                    'hasAttachments' => true,
                    'internetMessageHeaders' => [
                        ['name' => 'In-Reply-To', 'value' => '<parent@acme.test>'],
                        ['name' => 'References', 'value' => '<a@acme.test> <parent@acme.test>'],
                        ['name' => 'Auto-Submitted', 'value' => 'auto-replied'],
                    ],
                ]],
            ]),
            'https://graph.microsoft.com/v1.0/me/messages/graph-id-1/attachments' => FakePluginHttp::response([
                'value' => [[
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => 're-4711.pdf',
                    'contentType' => 'application/pdf',
                    'contentBytes' => base64_encode('PDF-INHALT'),
                ]],
            ]),
        ]);

        $messages = (new GraphMailboxGateway())->fetch($mailbox);

        $this->assertCount(1, $messages);
        $message = $messages[0];
        $this->assertSame('<msg-1@firma.example>', $message->messageId);
        $this->assertSame('graph-id-1', $message->externalId);
        $this->assertSame('kunde@acme.test', $message->fromEmail);
        $this->assertSame('Acme GmbH', $message->fromName);
        $this->assertSame('Bitte prüfen.', $message->body);
        $this->assertSame('<parent@acme.test>', $message->inReplyTo);
        $this->assertSame(['<a@acme.test>', '<parent@acme.test>'], $message->references);
        $this->assertTrue($message->isAutoSubmitted);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('PDF-INHALT', $message->attachments[0]->content);
    }

    public function test_fetch_returns_empty_without_graph_mail_connection(): void {
        $mailbox = $this->mailbox();
        $idle = FakePluginHttp::fake();

        $this->assertSame([], (new GraphMailboxGateway())->fetch($mailbox));
        $idle->assertNothingSent();
    }

    public function test_mark_processed_sets_read_flag_and_moves(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox(['processed_folder' => 'Verarbeitet']);

        $fake = FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/messages/graph-id-1' => FakePluginHttp::response(['id' => 'graph-id-1']),
            'https://graph.microsoft.com/v1.0/me/mailFolders*' => FakePluginHttp::response([
                'value' => [['id' => 'folder-77']],
            ]),
            'https://graph.microsoft.com/v1.0/me/messages/graph-id-1/move' => FakePluginHttp::response(['id' => 'moved'], 201),
        ]);

        $message = new \App\Services\Mail\ParsedMessage(
            messageId: '<msg-1@firma.example>',
            uid: 0,
            fromEmail: 'kunde@acme.test',
            fromName: 'Acme',
            subject: 'Re',
            body: 'x',
            receivedAt: now(),
            externalId: 'graph-id-1',
        );
        (new GraphMailboxGateway())->markProcessed($mailbox, $message);

        $fake->assertSent(fn ($request): bool => str_contains((string) $request->getUri(), '/messages/graph-id-1/move'));
    }

    public function test_transport_selector_routes_msgraph_mailboxes_to_graph_gateway(): void {
        $mailbox = $this->mailbox(); // ohne Graph-Mail-Verbindung → Graph-Gateway liefert []
        FakePluginHttp::fake();

        $selector = app(MailboxGateway::class);
        $this->assertInstanceOf(TransportSelectingMailboxGateway::class, $selector);
        $this->assertSame([], $selector->fetch($mailbox));
    }

    public function test_admin_form_requires_graph_connection_for_msgraph_mailboxes(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        // Ohne Graph-Mail-Verbindung → Fehler-Flash, kein Datensatz.
        $this->actingAs($admin)->post(route('admin.mail.connection.store'), [
            'name' => 'M365', 'transport' => 'msgraph', 'folder' => 'INBOX', 'active' => '1',
        ])->assertSessionHas('error');
        $this->assertSame(0, EmailConnection::query()->count());

        // Mit aktiver Verbindung → gespeichert, ohne IMAP-Zugangsdaten.
        $this->graphMailConnection();
        $this->actingAs($admin)->post(route('admin.mail.connection.store'), [
            'name' => 'M365', 'transport' => 'msgraph', 'folder' => 'INBOX', 'active' => '1',
        ])->assertSessionHas('success');

        $connection = EmailConnection::query()->firstOrFail();
        $this->assertTrue($connection->isMsgraph());
        $this->assertTrue($connection->isActive());
        $this->assertNull($connection->host);
        $this->assertNull($connection->username);
    }
}
