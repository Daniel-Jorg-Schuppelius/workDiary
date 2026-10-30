<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailNoteTextRefreshTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Models\Audit\AuditLog;
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Mail\EmailConnection;
use App\Services\Mail\{MailboxGateway, ParsedMessage};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeMailboxGateway;
use Tests\TestCase;

/**
 * Befund 2026-10-19: Bestandsnotizen aus HTML-Mails tragen den Text ohne
 * Zeilenstruktur; der Befehl liest die Mail erneut über ihre Message-ID.
 */
final class MailNoteTextRefreshTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Büro',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'buero@firma.test',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
            'created_by' => $this->orgUser()->id,
        ]);
    }

    private function mailNote(string $messageId, string $body, ?string $mailbox = 'Büro / INBOX'): CommunicationNote {
        $note = CommunicationNote::factory()->create([
            'organization_id' => $this->organization->id,
            'notable_type' => MorphMap::alias(Customer::class),
            'notable_id' => $this->customer->id,
            'type' => CommunicationNoteType::Email->value,
            'direction' => CommunicationDirection::Inbound->value,
            'subject' => 'AW: Mahnung',
            'body' => $body,
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'email',
            'external_type' => 'message',
            'referenceable_type' => $note->getMorphClass(),
            'referenceable_id' => $note->id,
            'external_id' => $messageId,
            'payload' => array_filter(['message_id' => $messageId, 'from' => ['email' => 'ralph@acme.test', 'name' => 'Ralph'], 'subject' => 'AW: Mahnung', 'body' => $body, 'received_at' => '2026-10-18T13:43:00+00:00', 'mailbox' => $mailbox]),
        ]);

        return $note;
    }

    private function message(string $messageId, string $body): ParsedMessage {
        return new ParsedMessage(messageId: $messageId, uid: 7, fromEmail: 'ralph@acme.test', fromName: 'Ralph', subject: 'AW: Mahnung', body: $body, receivedAt: Carbon::parse('2026-10-18 13:43:00'));
    }

    public function test_the_command_rewrites_flat_texts_from_the_mailbox_and_leaves_the_rest(): void {
        $flat = 'Hallo Simone, sorry, war durchgerutscht. Gruß Ralph Ralph Mustermann, Dipl.-Kfm.Geschäftsführender Gesellschafter';
        $structured = "Hallo Simone,\n\nsorry, war durchgerutscht.\n\nGruß Ralph\n\nRalph Mustermann, Dipl.-Kfm.\nGeschäftsführender Gesellschafter";
        $note = $this->mailNote('<kunde-1@acme.test>', $flat);
        $gone = $this->mailNote('<weg@acme.test>', 'Text ohne Mail im Postfach');
        $manual = CommunicationNote::factory()->create(['organization_id' => $this->organization->id, 'notable_type' => MorphMap::alias(Customer::class), 'notable_id' => $this->customer->id, 'body' => 'Telefonat &amp; Notiz']);
        $item = IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'email',
            'target_type' => 'customer',
            'external_type' => 'message',
            'external_id' => '<kunde-1@acme.test>',
            'dedupe_key' => 'message:<kunde-1@acme.test>',
            'case_type' => 'unmatched',
            'status' => 'resolved_linked',
            'remote_snapshot' => ['subject' => 'AW: Mahnung', 'body' => $flat],
        ]);
        $this->app->instance(MailboxGateway::class, new FakeMailboxGateway([$this->message('kunde-1@acme.test', $structured)]));

        $this->artisan('communication-notes:refresh-mail-text', ['--dry-run' => true])
            ->expectsOutputToContain('Probelauf: 1 erneuert, 0 unverändert, 1 nicht im Postfach')
            ->assertExitCode(0);
        $this->assertSame($flat, $note->refresh()->body);

        $this->artisan('communication-notes:refresh-mail-text')
            ->expectsOutputToContain('Erneuert: 1 erneuert, 0 unverändert, 1 nicht im Postfach')
            ->assertExitCode(0);

        $this->assertSame($structured, $note->refresh()->body);
        $this->assertSame('Text ohne Mail im Postfach', $gone->refresh()->body);
        $this->assertSame('Telefonat &amp; Notiz', $manual->refresh()->body);
        $this->assertSame($structured, ExternalReference::query()->withoutGlobalScopes()->forReferenceable($note)->sole()->payload['body'] ?? null);
        $this->assertSame($structured, $item->refresh()->remote_snapshot['body'] ?? null);
        $this->assertTrue(AuditLog::query()
            ->where('event', 'communication.mail_text_refreshed')
            ->where('auditable_type', MorphMap::stableKey(CommunicationNote::class))
            ->where('auditable_id', $note->id)
            ->exists());

        // Zweiter Lauf: nichts mehr zu tun.
        $this->artisan('communication-notes:refresh-mail-text')
            ->expectsOutputToContain('Erneuert: 0 erneuert, 1 unverändert, 1 nicht im Postfach')
            ->assertExitCode(0);
    }

    public function test_notes_without_a_named_mailbox_try_every_active_connection(): void {
        $note = $this->mailNote('<ohne-postfach@acme.test>', 'flach', mailbox: null);
        $this->app->instance(MailboxGateway::class, new FakeMailboxGateway([$this->message('<ohne-postfach@acme.test>', "Zeile 1\nZeile 2")]));

        $this->artisan('communication-notes:refresh-mail-text', ['--org' => $this->organization->id])->assertExitCode(0);

        $this->assertSame("Zeile 1\nZeile 2", $note->refresh()->body);
    }
}
