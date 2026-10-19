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

use App\Exceptions\{MailboxFolderMissingException, MailboxUnavailableException};
use App\Models\Mail\EmailConnection;
use App\Models\Platform\User;
use App\Plugins\Msgraph\Enums\MsgraphConnectionStatus;
use App\Plugins\Msgraph\Models\MsgraphMailConnection;
use App\Plugins\Msgraph\MsgraphPlugin;
use App\Plugins\Msgraph\Services\MsgraphMailboxGateway;
use App\Services\Mail\{MailboxCursor, MailboxGateway, ParsedMessage, TransportSelectingMailboxGateway};
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Mail-Eingang über Microsoft Graph (Feature 102; Abruf nach Stand MVP-1139,
 * Ordner MVP-1140, freigegebene Postfächer MVP-1141, angehängte Mails
 * MVP-1142): Delta-Abfrage des Ordners statt „ungelesen“, Nachricht mit
 * Kopfzeilen, Verarbeitung = gelesen + optional verschieben, Transport-Weiche.
 */
final class GraphMailboxGatewayTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('msgraph', ['client_id' => 'cid', 'client_secret' => 'sec']);
    }

    private function graphMailConnection(?string $scopes = null): MsgraphMailConnection {
        return MsgraphMailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'mail-token',
            'scopes' => $scopes,
            'status' => MsgraphConnectionStatus::Active,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function mailbox(array $attributes = []): EmailConnection {
        return EmailConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'M365-Postfach',
            'transport' => MsgraphPlugin::MAIL_TRANSPORT,
            'folder' => 'INBOX',
            'fetch_since' => '2026-08-01',
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function graphMessage(string $id, bool $attachments = false): array {
        return [
            'id' => $id,
            'internetMessageId' => '<' . $id . '@firma.example>',
            'subject' => 'Rechnung 4711',
            'from' => ['emailAddress' => ['address' => 'Kunde@Acme.test', 'name' => 'Acme GmbH']],
            'receivedDateTime' => '2026-08-05T09:00:00Z',
            'body' => ['contentType' => 'html', 'content' => "<div>Bitte <b>pr&uuml;fen</b>.<br>Danke&nbsp;&amp; Gru&szlig;</div>\r\n<p>Acme</p>"],
            'hasAttachments' => $attachments,
            'internetMessageHeaders' => [
                ['name' => 'In-Reply-To', 'value' => '<parent@acme.test>'],
                ['name' => 'References', 'value' => '<a@acme.test> <parent@acme.test>'],
                ['name' => 'Auto-Submitted', 'value' => 'auto-replied'],
            ],
        ];
    }

    /**
     * @param  Generator<int, ParsedMessage, mixed, MailboxCursor>  $generator
     * @return array{0: list<ParsedMessage>, 1: MailboxCursor}
     */
    private function drain(Generator $generator): array {
        $messages = iterator_to_array($generator, false);

        return [$messages, $generator->getReturn()];
    }

    public function test_fetch_reads_delta_and_maps_messages(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox();

        $fake = FakePluginHttp::fake([
            self::GRAPH . '/me/mailFolders/inbox/messages/delta*' => FakePluginHttp::response([
                'value' => [['id' => 'graph-id-1'], ['id' => 'gone', '@removed' => ['reason' => 'deleted']]],
                '@odata.deltaLink' => self::GRAPH . '/me/mailFolders/inbox/messages/delta?deltatoken=d1',
            ]),
            self::GRAPH . '/me/messages/graph-id-1/attachments' => FakePluginHttp::response([
                'value' => [[
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'id' => 'att-1',
                    'name' => 're-4711.pdf',
                    'contentType' => 'application/pdf',
                    'contentBytes' => base64_encode('PDF-INHALT'),
                ]],
            ]),
            self::GRAPH . '/me/messages/graph-id-1*' => FakePluginHttp::response($this->graphMessage('graph-id-1', true)),
        ]);

        [$messages, $cursor] = $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, MailboxCursor::start($mailbox), 25));

        $this->assertCount(1, $messages);
        $message = $messages[0];
        $this->assertSame('<graph-id-1@firma.example>', $message->messageId);
        $this->assertSame('graph-id-1', $message->externalId);
        $this->assertSame('kunde@acme.test', $message->fromEmail);
        $this->assertSame('Acme GmbH', $message->fromName);
        $this->assertSame("Bitte prüfen.\nDanke & Gruß\nAcme", $message->body);
        $this->assertSame('<parent@acme.test>', $message->inReplyTo);
        $this->assertSame(['<a@acme.test>', '<parent@acme.test>'], $message->references);
        $this->assertTrue($message->isAutoSubmitted);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('PDF-INHALT', $message->attachments[0]->content);

        $this->assertFalse($cursor->catchingUp);
        $this->assertSame('inbox', $cursor->folderId);
        $this->assertStringContainsString('deltatoken=d1', (string) $cursor->link);
        $fake->assertSent(fn ($request): bool => str_contains(urldecode((string) $request->getUri()), 'receivedDateTime ge 2026-08-01T00:00:00Z')
            && str_contains((string) $request->getUri(), 'changeType=created')
            && $request->getHeaderLine('Prefer') === 'odata.maxpagesize=25');
    }

    public function test_next_link_is_kept_until_the_pass_is_complete(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox();

        FakePluginHttp::fake([
            self::GRAPH . '/me/mailFolders/inbox/messages/delta?skiptoken=s1*' => FakePluginHttp::response([
                'value' => [['id' => 'm2']],
                '@odata.deltaLink' => self::GRAPH . '/me/mailFolders/inbox/messages/delta?deltatoken=d2',
            ]),
            self::GRAPH . '/me/mailFolders/inbox/messages/delta*' => FakePluginHttp::response([
                'value' => [['id' => 'm1']],
                '@odata.nextLink' => self::GRAPH . '/me/mailFolders/inbox/messages/delta?skiptoken=s1',
            ]),
            self::GRAPH . '/me/messages/m1*' => FakePluginHttp::response($this->graphMessage('m1')),
            self::GRAPH . '/me/messages/m2*' => FakePluginHttp::response($this->graphMessage('m2')),
        ]);

        [$messages, $cursor] = $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, MailboxCursor::start($mailbox), 1));

        $this->assertCount(1, $messages);
        $this->assertTrue($cursor->catchingUp);
        $this->assertStringContainsString('skiptoken=s1', (string) $cursor->link);
        $this->assertSame($cursor->link, $messages[0]->cursorAfter?->link);

        [$messages, $cursor] = $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, $cursor, 1));

        $this->assertSame('m2', $messages[0]->externalId);
        $this->assertFalse($cursor->catchingUp);
        $this->assertStringContainsString('deltatoken=d2', (string) $cursor->link);
    }

    public function test_expired_link_restarts_from_last_poll(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox(['last_polled_at' => Carbon::parse('2026-09-10 12:00:00')]);

        $fake = FakePluginHttp::fake([
            self::GRAPH . '/me/mailFolders/inbox/messages/delta?deltatoken=old*' => FakePluginHttp::response(['error' => ['code' => 'SyncStateNotFound']], 410),
            self::GRAPH . '/me/mailFolders/inbox/messages/delta*' => FakePluginHttp::response([
                'value' => [],
                '@odata.deltaLink' => self::GRAPH . '/me/mailFolders/inbox/messages/delta?deltatoken=fresh',
            ]),
        ]);
        $stale = MailboxCursor::start($mailbox)->with(['folderId' => 'inbox', 'link' => self::GRAPH . '/me/mailFolders/inbox/messages/delta?deltatoken=old', 'catchingUp' => false]);

        [$messages, $cursor] = $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, $stale, 25));

        $this->assertSame([], $messages);
        $this->assertStringContainsString('deltatoken=fresh', (string) $cursor->link);
        $this->assertNull($cursor->resumeFrom);
        $fake->assertSent(fn ($request): bool => str_contains(urldecode((string) $request->getUri()), 'receivedDateTime ge 2026-09-09T00:00:00Z'));
    }

    public function test_folder_path_is_resolved_through_child_folders(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox(['folder' => 'Posteingang/Rechnungen']);

        $fake = FakePluginHttp::fake([
            self::GRAPH . '/me/mailFolders/f-inbox/childFolders*' => FakePluginHttp::response([
                'value' => [['id' => 'f-invoices', 'displayName' => 'rechnungen', 'childFolderCount' => 0]],
            ]),
            self::GRAPH . '/me/mailFolders/f-invoices/messages/delta*' => FakePluginHttp::response([
                'value' => [],
                '@odata.deltaLink' => self::GRAPH . '/me/mailFolders/f-invoices/messages/delta?deltatoken=x',
            ]),
            self::GRAPH . '/me/mailFolders?*' => FakePluginHttp::response([
                'value' => [['id' => 'f-inbox', 'displayName' => 'Posteingang', 'childFolderCount' => 1]],
            ]),
        ]);

        [, $cursor] = $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, MailboxCursor::start($mailbox), 25));

        $this->assertSame('f-invoices', $cursor->folderId);
        $fake->assertSent(fn ($request): bool => str_contains((string) $request->getUri(), '/mailFolders/f-invoices/messages/delta'));
    }

    public function test_missing_folder_is_reported(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox(['folder' => 'Rechnugen']);
        FakePluginHttp::fake([
            self::GRAPH . '/me/mailFolders?*' => FakePluginHttp::response(['value' => [['id' => 'f1', 'displayName' => 'Rechnungen']]]),
        ]);

        $this->expectException(MailboxFolderMissingException::class);
        $this->expectExceptionMessage('Rechnugen');
        $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, MailboxCursor::start($mailbox), 25));
    }

    public function test_shared_mailbox_uses_users_path_and_needs_shared_scope(): void {
        $this->graphMailConnection('https://graph.microsoft.com/Mail.ReadWrite offline_access');
        $mailbox = $this->mailbox(['mailbox_address' => 'rechnungen@firma.de']);

        try {
            $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, MailboxCursor::start($mailbox), 25));
            $this->fail('Ohne Mail.ReadWrite.Shared darf kein Abruf laufen.');
        } catch (MailboxUnavailableException $e) {
            $this->assertStringContainsString('Mail.ReadWrite.Shared', $e->getMessage());
        }

        MsgraphMailConnection::query()->update(['scopes' => 'https://graph.microsoft.com/Mail.ReadWrite.Shared offline_access']);
        $fake = FakePluginHttp::fake([
            self::GRAPH . '/users/rechnungen%40firma.de/mailFolders/inbox/messages/delta*' => FakePluginHttp::response([
                'value' => [],
                '@odata.deltaLink' => self::GRAPH . '/users/rechnungen%40firma.de/mailFolders/inbox/messages/delta?deltatoken=s',
            ]),
        ]);

        $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, MailboxCursor::start($mailbox), 25));

        $fake->assertSent(fn ($request): bool => str_contains((string) $request->getUri(), '/users/rechnungen%40firma.de/mailFolders/inbox/messages/delta'));
    }

    public function test_attached_mail_contributes_its_files(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox();

        FakePluginHttp::fake([
            self::GRAPH . '/me/mailFolders/inbox/messages/delta*' => FakePluginHttp::response([
                'value' => [['id' => 'fwd']],
                '@odata.deltaLink' => self::GRAPH . '/me/mailFolders/inbox/messages/delta?deltatoken=d',
            ]),
            self::GRAPH . '/me/messages/fwd/attachments/item-1*' => FakePluginHttp::response([
                '@odata.type' => '#microsoft.graph.itemAttachment',
                'item' => ['attachments' => [[
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => 'rechnung.pdf',
                    'contentType' => 'application/pdf',
                    'contentBytes' => base64_encode('%PDF-1.4 innen'),
                ]]],
            ]),
            self::GRAPH . '/me/messages/fwd/attachments' => FakePluginHttp::response([
                'value' => [['@odata.type' => '#microsoft.graph.itemAttachment', 'id' => 'item-1', 'name' => 'Rechnung Mai']],
            ]),
            self::GRAPH . '/me/messages/fwd*' => FakePluginHttp::response($this->graphMessage('fwd', true)),
        ]);

        [$messages] = $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, MailboxCursor::start($mailbox), 25));

        $this->assertCount(1, $messages[0]->attachments);
        $this->assertSame('rechnung.pdf', $messages[0]->attachments[0]->filename);
        $this->assertSame('%PDF-1.4 innen', $messages[0]->attachments[0]->content);
    }

    public function test_fetch_fails_visibly_without_graph_mail_connection(): void {
        $mailbox = $this->mailbox();
        $idle = FakePluginHttp::fake();

        try {
            $this->drain((new MsgraphMailboxGateway())->fetch($mailbox, MailboxCursor::start($mailbox), 25));
            $this->fail('Ohne Verbindung muss der Abruf scheitern.');
        } catch (MailboxUnavailableException) {
            $idle->assertNothingSent();
        }
    }

    public function test_mark_processed_sets_read_flag_and_moves_into_resolved_folder(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox(['processed_folder' => 'Posteingang/Erledigt']);

        $fake = FakePluginHttp::fake([
            self::GRAPH . '/me/mailFolders/f-inbox/childFolders*' => FakePluginHttp::response(['value' => [['id' => 'folder-77', 'displayName' => 'Erledigt']]]),
            self::GRAPH . '/me/mailFolders?*' => FakePluginHttp::response(['value' => [['id' => 'f-inbox', 'displayName' => 'Posteingang']]]),
            self::GRAPH . '/me/messages/graph-id-1/move' => FakePluginHttp::response(['id' => 'moved'], 201),
            self::GRAPH . '/me/messages/graph-id-1' => FakePluginHttp::response(['id' => 'graph-id-1']),
        ]);

        (new MsgraphMailboxGateway())->markProcessed($mailbox, $this->parsed('graph-id-1'));

        $fake->assertSent(fn ($request): bool => $request->getMethod() === 'PATCH' && str_ends_with((string) $request->getUri(), '/messages/graph-id-1'));
        $fake->assertSent(fn ($request): bool => str_contains((string) $request->getUri(), '/messages/graph-id-1/move')
            && str_contains((string) $request->getBody(), 'folder-77'));
    }

    public function test_missing_processed_folder_is_reported(): void {
        $this->graphMailConnection();
        $mailbox = $this->mailbox(['processed_folder' => 'Erledigt']);
        FakePluginHttp::fake([
            self::GRAPH . '/me/mailFolders?*' => FakePluginHttp::response(['value' => []]),
        ]);

        $this->expectException(MailboxFolderMissingException::class);
        (new MsgraphMailboxGateway())->markProcessed($mailbox, $this->parsed('graph-id-1'));
    }

    public function test_transport_selector_routes_msgraph_mailboxes_to_graph_gateway(): void {
        $mailbox = $this->mailbox(); // ohne Graph-Mail-Verbindung → Meldung des Graph-Gateways
        FakePluginHttp::fake();

        $selector = app(MailboxGateway::class);
        $this->assertInstanceOf(TransportSelectingMailboxGateway::class, $selector);
        $this->expectException(MailboxUnavailableException::class);
        $this->drain($selector->fetch($mailbox, MailboxCursor::start($mailbox), 25));
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
        $this->assertFalse($connection->usesImap());
        $this->assertTrue($connection->isActive());
        $this->assertNull($connection->host);
        $this->assertNull($connection->username);
    }

    private function parsed(string $graphId): ParsedMessage {
        return new ParsedMessage(
            messageId: '<' . $graphId . '@firma.example>',
            uid: 0,
            fromEmail: 'kunde@acme.test',
            fromName: 'Acme',
            subject: 'Re',
            body: 'x',
            receivedAt: now(),
            externalId: $graphId,
        );
    }
}
