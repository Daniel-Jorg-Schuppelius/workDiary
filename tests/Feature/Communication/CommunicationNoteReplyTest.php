<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteReplyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType};
use App\Enums\Invoicing\InvoiceInquiryStatus;
use App\Mail\CommunicationNoteReplyMail;
use App\Models\Audit\AuditLog;
use App\Models\Communication\CommunicationNote;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\InvoiceInquiry;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\User;
use App\Services\Mail\MailSignature;
use App\Settings\SettingScope;
use App\Support\{MorphMap, Setting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Antwort auf eine eingegangene E-Mail-Notiz (Befund 2026-10-19): Mail mit
 * Faden-Kopfzeilen, ausgehende Notiz beim Kunden, Versandverweis, Audit und
 * die offene Rechnungsnachfrage gilt als beantwortet.
 */
final class CommunicationNoteReplyTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = $this->orgUser();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Acme GmbH']);
    }

    /** @param array<string, mixed> $payload */
    private function inboundNote(array $payload = []): CommunicationNote {
        $note = CommunicationNote::factory()->create([
            'organization_id' => $this->organization->id,
            'notable_type' => MorphMap::alias(Customer::class),
            'notable_id' => $this->customer->id,
            'type' => CommunicationNoteType::Email->value,
            'direction' => CommunicationDirection::Inbound->value,
            'subject' => 'AW: Mahnung RE-2025-0865',
            'body' => "Hallo,\n\nsoeben überwiesen.\nGruß Ralph",
            'created_by_user_id' => $this->user->id,
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'email',
            'external_type' => 'message',
            'referenceable_type' => $note->getMorphClass(),
            'referenceable_id' => $note->id,
            'external_id' => '<kunde-1@acme.test>',
            'payload' => $payload + [
                'message_id' => '<kunde-1@acme.test>',
                'from' => ['email' => 'ralph@acme.test', 'name' => 'Ralph Hartig'],
                'subject' => 'AW: Mahnung RE-2025-0865',
                'received_at' => '2026-10-18T13:43:00+00:00',
                'mailbox' => 'Büro / INBOX',
            ],
        ]);

        return $note;
    }

    private function manualNote(): CommunicationNote {
        return CommunicationNote::factory()->create([
            'organization_id' => $this->organization->id,
            'notable_type' => MorphMap::alias(Customer::class),
            'notable_id' => $this->customer->id,
            'type' => CommunicationNoteType::Call->value,
            'direction' => CommunicationDirection::Outbound->value,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_the_note_dialog_shows_the_mail_and_offers_a_reply(): void {
        $note = $this->inboundNote();

        $response = $this->actingAs($this->user)->get(route('communication-notes.show', $note));

        $response->assertOk();
        $response->assertSee('ralph@acme.test');
        $response->assertSee('Büro / INBOX');
        $response->assertSee(route('communication-notes.reply', $note), false);

        $this->actingAs($this->user)->get(route('communication-notes.show', $this->manualNote()))
            ->assertOk()
            ->assertDontSee('communication-notes/' . $this->manualNote()->sqid . '/reply');
    }

    public function test_the_customer_panel_offers_show_and_reply_only_for_mail_notes(): void {
        $note = $this->inboundNote();
        $manual = $this->manualNote();

        $response = $this->actingAs($this->user)->get(route('customers.show', $this->customer));

        $response->assertOk();
        $response->assertSee(route('communication-notes.reply', $note), false);
        $response->assertSee(route('communication-notes.show', $note), false);
        $response->assertSee(route('communication-notes.show', $manual), false);
        $response->assertDontSee(route('communication-notes.reply', $manual), false);
    }

    public function test_the_reply_dialog_is_prefilled_with_sender_and_subject(): void {
        $note = $this->inboundNote();

        $response = $this->actingAs($this->user)->get(route('communication-notes.reply', $note));

        $response->assertOk();
        $response->assertSee('value="ralph@acme.test"', false);
        $response->assertSee('value="AW: Mahnung RE-2025-0865"', false);
        $response->assertSee('soeben überwiesen.');
        $response->assertSee($this->user->name);
    }

    public function test_reply_sends_the_mail_records_the_outbound_note_and_answers_the_inquiry(): void {
        Mail::fake();
        EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Büro',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'buero@firma.test',
            'password' => 'secret',
            'folder' => 'INBOX',
            'mailbox_address' => 'buero@firma.test',
            'active' => true,
            'created_by' => $this->user->id,
        ]);
        $this->user->setPreference(MailSignature::PREFERENCE, "Daniel S.\nTel. 1");
        Setting::set(MailSignature::FOOTER_KEY, 'Firma GmbH · Musterstraße 1', SettingScope::Organization, $this->organization);
        $note = $this->inboundNote();
        $inquiry = InvoiceInquiry::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'communication_note_id' => $note->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('communication-notes.reply.send', $note), [
            'recipients' => 'ralph@acme.test, buchhaltung@acme.test',
            'cc' => '',
            'subject' => 'AW: Mahnung RE-2025-0865',
            'body' => 'Danke, der Betrag ist angekommen.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(CommunicationNoteReplyMail::class, static fn (CommunicationNoteReplyMail $mail): bool => $mail->hasTo('ralph@acme.test')
            && $mail->hasTo('buchhaltung@acme.test')
            && $mail->mailSubject === 'AW: Mahnung RE-2025-0865'
            && $mail->inReplyTo === 'kunde-1@acme.test'
            && $mail->replyToAddress === 'buero@firma.test'
            && str_contains($mail->messageText, "Danke, der Betrag ist angekommen.\n\nDaniel S.\nTel. 1\n\nFirma GmbH · Musterstraße 1\n\n")
            && str_contains($mail->messageText, '> soeben überwiesen.'));

        $reply = CommunicationNote::query()->where('direction', CommunicationDirection::Outbound->value)->sole();
        $this->assertSame(CommunicationNoteType::Email, $reply->type);
        $this->assertSame('AW: Mahnung RE-2025-0865', $reply->subject);
        $this->assertSame('Danke, der Betrag ist angekommen.', $reply->body);
        $this->assertSame($this->customer->id, $reply->notable_id);
        $this->assertSame($this->user->id, $reply->created_by_user_id);

        $origin = $reply->mailOrigin();
        $this->assertNotNull($origin);
        $this->assertSame(CommunicationDirection::Outbound, $origin->direction);
        $this->assertSame(['ralph@acme.test', 'buchhaltung@acme.test'], $origin->to);
        $reference = ExternalReference::query()->withoutGlobalScopes()->forReferenceable($reply)->sole();
        $this->assertSame('kunde-1@acme.test', $reference->payload['in_reply_to'] ?? null);
        $this->assertSame(['ralph@acme.test', 'buchhaltung@acme.test'], $reference->payload['to'] ?? null);
        $this->assertMatchesRegularExpression('/^<[0-9a-f-]{36}@[^>]+>$/', (string) $reference->external_id);

        $inquiry->refresh();
        $this->assertSame(InvoiceInquiryStatus::Answered, $inquiry->status);
        $this->assertSame($this->user->id, $inquiry->answered_by);

        // Die ausgehende Notiz zeigt Empfänger und Antwortadresse, bietet aber kein „Antworten“.
        $this->actingAs($this->user)->get(route('communication-notes.show', $reply))
            ->assertOk()
            ->assertSee('buchhaltung@acme.test')
            ->assertSee('buero@firma.test')
            ->assertDontSee(route('communication-notes.reply', $reply), false);

        $this->assertTrue(AuditLog::query()
            ->where('event', 'communication.replied')
            ->where('auditable_type', MorphMap::stableKey(CommunicationNote::class))
            ->where('auditable_id', $note->id)
            ->exists());
    }

    public function test_notes_without_a_mail_behind_them_cannot_be_replied(): void {
        Mail::fake();
        $note = $this->manualNote();

        $this->actingAs($this->user)->get(route('communication-notes.reply', $note))->assertNotFound();
        $this->actingAs($this->user)->post(route('communication-notes.reply.send', $note), [
            'recipients' => 'x@acme.test', 'subject' => 'Re', 'body' => 'Text',
        ])->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_invalid_recipients_are_rejected_before_anything_is_sent(): void {
        Mail::fake();
        $note = $this->inboundNote();

        $response = $this->actingAs($this->user)->from(route('communication-notes.index'))->post(route('communication-notes.reply.send', $note), [
            'recipients' => 'kein-postfach',
            'subject' => 'AW: Mahnung',
            'body' => 'Text',
        ]);

        $response->assertSessionHasErrors('recipients');
        Mail::assertNothingSent();
        $this->assertSame(1, CommunicationNote::query()->count());
    }
}
