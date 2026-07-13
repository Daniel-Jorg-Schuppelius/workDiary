<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailInboxActionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Mail;

use App\Models\{Customer, Document, EmailConnection, IntegrationInboxItem, ServiceQueue, ServiceTicket, User};
use App\Services\Mail\{MailAttachment, MailIntakeService, ParsedMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 056/065, MVP-343: die vorhandenen Auflösungswege
 * bookAsServiceTicket/importAttachmentsToDms sind als Aktionen in der
 * Mail-Inbox exponiert. Prüft Ticket aus Mail (idempotent), DMS-Übernahme
 * mit Anhängen, Rechte (nur Admin) und Org-Isolation (404 cross-org).
 */
final class MailInboxActionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function connection(): EmailConnection {
        return EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
        ]);
    }

    /**
     * @param  list<MailAttachment>  $attachments
     */
    private function intakeMail(string $messageId = '<action@x>', string $subject = 'Wartungsanfrage', array $attachments = []): IntegrationInboxItem {
        $message = new ParsedMessage(
            messageId: $messageId,
            uid: 7,
            fromEmail: 'kunde@acme.test',
            fromName: 'Absender',
            subject: $subject,
            body: 'Bitte um Rückmeldung.',
            receivedAt: Carbon::parse('2026-07-12 09:00:00'),
            attachments: $attachments,
        );
        app(MailIntakeService::class)->intake($this->organization, $this->connection(), $message);

        return IntegrationInboxItem::query()->where('plugin_id', MailIntakeService::PLUGIN_ID)->firstOrFail();
    }

    public function test_admin_books_mail_as_service_ticket_idempotent(): void {
        ServiceQueue::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'is_default' => true,
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $item = $this->intakeMail();

        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.book-ticket'), ['item' => $item->sqid])
            ->assertRedirect()
            ->assertSessionHas('success', __('mail.flash.ticket_booked'));

        $ticket = ServiceTicket::query()->firstOrFail();
        $this->assertSame('Wartungsanfrage', $ticket->title);
        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertSame('email', $ticket->source->value);

        $item->refresh();
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_LINKED, $item->status);

        // Idempotenz: ein zweiter Klick erzeugt KEIN zweites Ticket.
        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.book-ticket'), ['item' => $item->sqid])
            ->assertRedirect()
            ->assertSessionHas('error', __('mail.flash.already_resolved'));
        $this->assertSame(1, ServiceTicket::query()->count());
    }

    public function test_ticket_booking_requires_admin(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $item = $this->intakeMail();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('admin.mail.inbox.book-ticket'), ['item' => $item->sqid])
            ->assertForbidden();

        $this->assertSame(0, ServiceTicket::query()->count());
    }

    public function test_ticket_booking_is_org_isolated(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $item = $this->intakeMail();

        $foreignAdmin = User::factory()->admin()->create();

        $this->actingAs($foreignAdmin)
            ->post(route('admin.mail.inbox.book-ticket'), ['item' => $item->sqid])
            ->assertNotFound();

        $this->assertSame(0, ServiceTicket::query()->withoutGlobalScopes()->count());
    }

    public function test_admin_imports_attachments_to_dms_idempotent(): void {
        Storage::fake('local');
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $item = $this->intakeMail('<dms-action@x>', 'Unterlagen', [
            new MailAttachment('wartungsbericht.pdf', 'application/pdf', '%PDF-1.4 bericht'),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.import-dms'), ['item' => $item->sqid])
            ->assertRedirect()
            ->assertSessionHas('success', __('mail.dms.imported', ['count' => 1]));

        $document = Document::query()->firstOrFail();
        $this->assertSame('wartungsbericht.pdf', $document->title);
        $this->assertSame($customer->getMorphClass(), $document->documentable_type);
        $this->assertSame($customer->id, $document->documentable_id);

        // Idempotenz: erneute Übernahme legt nichts doppelt an.
        $this->actingAs($this->admin)
            ->post(route('admin.mail.inbox.import-dms'), ['item' => $item->sqid])
            ->assertRedirect()
            ->assertSessionHas('error', __('mail.dms.none'));
        $this->assertSame(1, Document::query()->count());
    }

    public function test_dms_import_is_org_isolated(): void {
        Storage::fake('local');
        Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $item = $this->intakeMail('<dms-cross@x>', 'Unterlagen', [
            new MailAttachment('geheim.pdf', 'application/pdf', '%PDF-1.4 geheim'),
        ]);

        $foreignAdmin = User::factory()->admin()->create();

        $this->actingAs($foreignAdmin)
            ->post(route('admin.mail.inbox.import-dms'), ['item' => $item->sqid])
            ->assertNotFound();

        $this->assertSame(0, Document::query()->withoutGlobalScopes()->count());
    }

    public function test_inbox_page_offers_ticket_and_dms_actions_for_mail_items(): void {
        Storage::fake('local');
        Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'kunde@acme.test']);
        $this->intakeMail('<ui@x>', 'Wartungsanfrage', [
            new MailAttachment('rechnung.pdf', 'application/pdf', '%PDF-1.4 hallo'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.integration.inbox'))
            ->assertOk()
            ->assertSee(route('admin.mail.inbox.book-ticket'), false)
            ->assertSee(__('mail.inbox.book_ticket_action'))
            ->assertSee(route('admin.mail.inbox.import-dms'), false)
            ->assertSee(__('mail.dms.action'));
    }
}
