<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeleteActionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\UI;

use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Protocol\ProtocolStatus;
use App\Exceptions\PartyDeletionBlockedException;
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\{Invoice, InvoiceSchedule};
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Models\Safety\SafetyEvent;
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Supplier\{Supplier, SupplierCredential, SupplierCredentialType};
use App\Support\{EntityUrl, MorphMap};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Löschwege mit Knopf auf der Detailseite (Vollscan 2026-10-21, Entscheidung
 * des Inhabers) und die Sperre gegen das Löschen von Parteien mit Belegen:
 * die Fremdschlüssel löschten ausgestellte Rechnungen sonst mit.
 */
final class DeleteActionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $billing;

    private User $member;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->billing = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_billing_deletes_a_customer_with_only_its_default_project(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertSame(1, $customer->projects()->where('is_default', true)->count());
        $destroy = route('customers.destroy', $customer);

        $this->assertDeleteForm($this->actingAs($this->billing)->get(route('customers.show', $customer))->assertOk()->getContent(), $destroy, true);
        $this->assertDeleteForm($this->actingAs($this->member)->get(route('customers.show', $customer))->getContent(), $destroy, false);

        $this->actingAs($this->billing)->delete($destroy)->assertRedirect(route('customers.index'));
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_customer_with_an_issued_invoice_is_not_deleted(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $invoice = Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'status' => InvoiceStatus::Issued]);

        $this->actingAs($this->admin)->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertSame(1, $customer->projects()->where('is_default', true)->count(), 'das Standardprojekt bleibt');

        Sanctum::actingAs($this->admin, ['*']);
        $this->deleteJson(route('api.customers.destroy', $customer))->assertStatus(409);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);

        $this->expectException(PartyDeletionBlockedException::class);
        $customer->delete();
    }

    public function test_supplier_with_a_stored_credential_is_not_deleted(): void {
        $this->seed(\Database\Seeders\SupplierCredentialCatalogSeeder::class);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $destroy = route('suppliers.destroy', $supplier);

        $this->assertDeleteForm($this->actingAs($this->billing)->get(route('suppliers.show', $supplier))->assertOk()->getContent(), $destroy, true);

        SupplierCredential::query()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => $supplier->id,
            'supplier_credential_type_id' => SupplierCredentialType::query()->whereNull('organization_id')->firstOrFail()->id,
            'issued_on' => now()->subYear()->toDateString(),
        ]);
        $this->actingAs($this->billing)->delete($destroy)->assertRedirect(route('suppliers.show', $supplier))->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_foreign_customer_and_invoice_schedule_offer_deletion(): void {
        $foreign = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id]);
        $destroy = route('foreign-customers.destroy', $foreign);
        $this->assertDeleteForm($this->actingAs($this->billing)->get(route('foreign-customers.show', $foreign))->assertOk()->getContent(), $destroy, true);
        $this->assertDeleteForm($this->actingAs($this->member)->get(route('foreign-customers.show', $foreign))->getContent(), $destroy, false);
        $this->actingAs($this->billing)->delete($destroy)->assertRedirect();
        $this->assertDatabaseMissing('foreign_customers', ['id' => $foreign->id]);

        $schedule = InvoiceSchedule::factory()->create(['organization_id' => $this->organization->id]);
        $destroy = route('invoice-schedules.destroy', $schedule);
        $this->assertDeleteForm($this->actingAs($this->admin)->get(route('invoice-schedules.show', $schedule))->assertOk()->getContent(), $destroy, true);
        $this->actingAs($this->admin)->delete($destroy)->assertRedirect();
        $this->assertDatabaseMissing('invoice_schedules', ['id' => $schedule->id]);
    }

    public function test_ticket_and_safety_event_offer_deletion_only_with_permission(): void {
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->organization->id]);
        $destroy = route('service-tickets.destroy', $ticket);
        $this->assertDeleteForm($this->actingAs($this->admin)->get(route('service-tickets.show', $ticket))->assertOk()->getContent(), $destroy, true);
        $this->actingAs($this->admin)->delete($destroy)->assertRedirect(route('service-tickets.index'));
        $this->assertDatabaseMissing('service_tickets', ['id' => $ticket->id]);

        $event = SafetyEvent::factory()->create(['organization_id' => $this->organization->id]);
        $destroy = route('safety-events.destroy', $event);
        $this->assertDeleteForm($this->actingAs($this->admin)->get(route('safety-events.show', $event))->assertOk()->getContent(), $destroy, true);
        $this->actingAs($this->admin)->delete($destroy)->assertRedirect(route('safety-events.index'));
        $this->assertSoftDeleted('safety_events', ['id' => $event->id]);
    }

    public function test_draft_protocol_is_deleted_and_returns_to_its_carrier(): void {
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id]);
        $protocol = Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'subject_type' => MorphMap::alias(DiaryEntry::class),
            'subject_id' => $entry->id,
        ]);
        $destroy = route('protocols.destroy', $protocol);

        $this->assertDeleteForm($this->actingAs($this->admin)->get(route('protocols.show', $protocol))->assertOk()->getContent(), $destroy, true);
        $this->actingAs($this->admin)->delete($destroy)->assertRedirect((string) EntityUrl::for($entry));
        $this->assertDatabaseMissing('protocols', ['id' => $protocol->id]);

        $signed = Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => ProtocolStatus::Signed->value,
        ]);
        $this->assertDeleteForm($this->actingAs($this->admin)->get(route('protocols.show', $signed))->assertOk()->getContent(), route('protocols.destroy', $signed), false);
        $this->actingAs($this->admin)->delete(route('protocols.destroy', $signed))->assertSessionHasErrors('status');
        $this->assertDatabaseHas('protocols', ['id' => $signed->id]);
    }

    private function assertDeleteForm(string|false $html, string $action, bool $expected): void {
        $pattern = '#<form method="POST" action="' . preg_quote(e($action), '#') . '"[^>]*>\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="DELETE">#';
        $this->assertSame($expected, preg_match($pattern, (string) $html) === 1, ($expected ? 'Löschformular fehlt: ' : 'Löschformular unerwartet: ') . $action);
    }
}
