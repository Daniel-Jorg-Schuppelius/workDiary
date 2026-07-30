<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPortalTicketAttachmentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\ServiceTicket\TicketMessageKind;
use App\Models\{Attachment, Customer, Organization, ServiceTicket, ServiceTicketMessage, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * W5.1 — Ticket-Anhang-Download im Kundenportal: das Portal liefert NUR
 * kundensichtbare Anhänge eigener Tickets (direkt am Ticket oder an einer
 * kundensichtbaren Nachricht). Leak-Tests nach dem Muster
 * {@see CustomerPortalDocumentsTest}: intern/fremder Kunde/interne Notiz/
 * fremde Org sind nie ladbar, Gast landet am Login.
 */
class CustomerPortalTicketAttachmentsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private User $portalUser;

    private User $internalUser;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->internalUser = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
    }

    private function ownTicket(array $overrides = []): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            ...$overrides,
        ]);
    }

    /** Nachricht am Ticket (kind steuert die Portal-Sichtbarkeit). */
    private function makeMessage(ServiceTicket $ticket, TicketMessageKind $kind): ServiceTicketMessage {
        return ServiceTicketMessage::query()->create([
            'organization_id' => $ticket->organization_id,
            'service_ticket_id' => $ticket->id,
            'kind' => $kind->value,
            'body' => 'Nachricht (' . $kind->value . ')',
            'channel' => 'manual',
        ]);
    }

    /** Anhang samt Datei am Träger (Ticket oder Nachricht); Pfad nur in der DB. */
    private function makeAttachment(Model $attachable, bool $visible, string $name = 'anleitung.pdf'): Attachment {
        $path = 'attachments/tickets/' . Str::uuid()->toString() . '.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 ' . $name);

        return Attachment::query()->create([
            'organization_id' => (int) $attachable->getAttribute('organization_id'),
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => (int) $attachable->getKey(),
            'user_id' => $this->internalUser->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $name,
            'mime' => 'application/pdf',
            'size' => 1024,
            'customer_visible' => $visible,
        ]);
    }

    public function test_download_serves_visible_attachment_of_own_ticket(): void {
        $ticket = $this->ownTicket();
        $attachment = $this->makeAttachment($ticket, true, 'eigenes-dokument.pdf');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.tickets.attachments.download', [$ticket, $attachment]))
            ->assertOk()
            ->assertDownload('eigenes-dokument.pdf');
    }

    public function test_download_serves_visible_attachment_of_public_reply(): void {
        $ticket = $this->ownTicket();
        $message = $this->makeMessage($ticket, TicketMessageKind::PublicReply);
        $attachment = $this->makeAttachment($message, true, 'antwort-anhang.pdf');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.tickets.attachments.download', [$ticket, $attachment]))
            ->assertOk()
            ->assertDownload('antwort-anhang.pdf');
    }

    public function test_download_rejects_internal_attachment_of_own_ticket(): void {
        $ticket = $this->ownTicket();
        $attachment = $this->makeAttachment($ticket, false, 'intern.pdf');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.tickets.attachments.download', [$ticket, $attachment]))
            ->assertNotFound();
    }

    public function test_download_rejects_attachment_of_internal_note_despite_visible_flag(): void {
        // Doppelter Riegel: selbst ein (fehlerhaft) freigegebener Anhang einer
        // internen Notiz darf das Portal nie erreichen.
        $ticket = $this->ownTicket();
        $note = $this->makeMessage($ticket, TicketMessageKind::InternalNote);
        $attachment = $this->makeAttachment($note, true, 'geheime-notiz.pdf');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.tickets.attachments.download', [$ticket, $attachment]))
            ->assertNotFound();
    }

    public function test_download_rejects_attachment_of_foreign_customer_ticket(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignTicket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $attachment = $this->makeAttachment($foreignTicket, true, 'fremd.pdf');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.tickets.attachments.download', [$foreignTicket, $attachment]))
            ->assertNotFound();
    }

    public function test_download_rejects_attachment_not_belonging_to_ticket(): void {
        // Paar-Bindung: eigener Ticket-Parameter + Anhang eines fremden Tickets → 404.
        $ownTicket = $this->ownTicket();
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreignTicket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $foreignAttachment = $this->makeAttachment($foreignTicket, true, 'fremd.pdf');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.tickets.attachments.download', [$ownTicket, $foreignAttachment]))
            ->assertNotFound();
    }

    public function test_portal_ticket_attachments_are_organization_isolated(): void {
        $otherOrg = Organization::factory()->create();
        $otherCustomer = Customer::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignTicket = ServiceTicket::factory()->create([
            'organization_id' => $otherOrg->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $attachment = $this->makeAttachment($foreignTicket, true, 'fremde-org.pdf');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.tickets.attachments.download', [$foreignTicket, $attachment]))
            ->assertNotFound();
    }

    public function test_ticket_view_links_visible_attachment_and_hides_internal(): void {
        $ticket = $this->ownTicket();
        $visible = $this->makeAttachment($ticket, true, 'sichtbar.pdf');
        $this->makeAttachment($ticket, false, 'unsichtbar.pdf');

        $response = $this->actingAs($this->portalUser, 'customer')->get(route('customer.tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee(route('customer.tickets.attachments.download', [$ticket, $visible]));
        $response->assertSee('sichtbar.pdf');
        $response->assertDontSee('unsichtbar.pdf');
    }

    public function test_guest_cannot_download_ticket_attachments(): void {
        $ticket = $this->ownTicket();
        $attachment = $this->makeAttachment($ticket, true);

        $this->get(route('customer.tickets.attachments.download', [$ticket, $attachment]))
            ->assertRedirect(route('customer.login'));
    }
}
