<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentCarrierAccessTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Enums\ServiceTicket\{ServiceTicketStatus, TicketMessageKind};
use App\Models\{Attachment, ServiceTicket, ServiceTicketMessage, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-17 (idor-attachment-5): Für Träger ohne eigene
 * Policy fiel `AttachmentPolicy::view` auf „gleiche Organisation" zurück.
 * Mailanhänge interner Ticket-Notizen waren damit über die Anhang-Kennung
 * abrufbar, obwohl die Ticket-Policy den Vorgang verwehrt.
 */
final class AttachmentCarrierAccessTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_ticket_message_attachment_follows_the_ticket_policy(): void {
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => ServiceTicketStatus::InProgress,
        ]);
        $message = ServiceTicketMessage::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'kind' => TicketMessageKind::InternalNote->value,
            'body' => 'Interner Verdacht',
            'channel' => 'mail',
        ]);
        $attachment = Attachment::query()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => $message->getMorphClass(),
            'attachable_id' => $message->id,
            'disk' => 'local',
            'path' => 'attachments/geheim.pdf',
            'original_name' => 'geheim.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by_user_id' => null,
        ]);

        $ohneTicketrecht = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->assertFalse(Gate::forUser($ohneTicketrecht)->allows('view', $attachment));

        $mitTicketrecht = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $mitTicketrecht->givePermissionTo(\App\Enums\User\Permission::ServiceTicketView->value);
        $this->assertTrue(Gate::forUser($mitTicketrecht->fresh())->allows('view', $attachment));
    }
}
