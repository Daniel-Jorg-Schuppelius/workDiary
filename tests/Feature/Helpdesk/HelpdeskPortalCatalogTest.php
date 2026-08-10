<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskPortalCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\Form\FormFieldType;
use App\Models\{BusinessService, Customer, FormTemplate, RequestItem, ServiceOffering, ServiceQueue, ServiceRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Feature 065, MVP-154: Portal-Bestellstrecke — nur portal-sichtbare
 * Einträge (visibility.portal, optional customer_ids), Bestellung friert
 * Formular- und Katalog-Snapshot ein (Katalogänderung schreibt nie um),
 * Ticket entsteht in der Portal-Queue mit Source customer_portal und
 * Portal-User als Requester; Bestellstatus ist im Portal sichtbar.
 */
final class HelpdeskPortalCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private User $agent;

    private ServiceQueue $portalQueue;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        // Portal-Bereichsfreigaben (MVP-511): Bestandstests laufen im Kompat-Vollumfang.
        $this->allowPortal($this->customer);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->portalQueue = ServiceQueue::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Portal',
            'visibility' => 'portal',
        ]);
        ServiceQueue::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Intern',
            'is_default' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function item(array $overrides = []): RequestItem {
        $service = BusinessService::query()->firstOrCreate(
            ['organization_id' => $this->organization->id, 'name' => 'Arbeitsplatz'],
        );
        $offering = ServiceOffering::query()->create([
            'organization_id' => $this->organization->id,
            'business_service_id' => $service->id,
            'name' => 'Angebot ' . fake()->unique()->numberBetween(1, 999999),
        ]);

        return RequestItem::query()->create([
            'organization_id' => $this->organization->id,
            'service_offering_id' => $offering->id,
            'name' => 'Notebook bestellen',
            'visibility' => ['portal' => true],
            ...$overrides,
        ]);
    }

    private function formTemplate(): FormTemplate {
        return FormTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->agent->id,
            'fields' => [
                ['key' => 'cpu', 'label' => 'Gewünschte CPU', 'type' => FormFieldType::Text->value, 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
                ['key' => 'os', 'label' => 'Betriebssystem', 'type' => FormFieldType::Select->value, 'required' => false, 'options' => ['Linux', 'Windows'], 'help' => null, 'unit' => null],
            ],
        ]);
    }

    private function actingAsPortalUser(): static {
        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorSetup::class);

        return $this->actingAs($this->portalUser, 'customer');
    }

    public function test_portal_lists_only_visible_items(): void {
        $this->item(['name' => 'Portal-Eintrag']);
        $this->item(['name' => 'Interner Eintrag', 'visibility' => null]);
        $this->item(['name' => 'Inaktiver Eintrag', 'active' => false]);

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->item([
            'name' => 'Fremdkunden-Eintrag',
            'visibility' => ['portal' => true, 'customer_ids' => [(int) $otherCustomer->id]],
        ]);
        $this->item([
            'name' => 'Eigenkunden-Eintrag',
            'visibility' => ['portal' => true, 'customer_ids' => [(int) $this->customer->id]],
        ]);

        $this->actingAsPortalUser()
            ->get(route('customer.catalog.index'))
            ->assertOk()
            ->assertSee('Portal-Eintrag')
            ->assertSee('Eigenkunden-Eintrag')
            ->assertDontSee('Interner Eintrag')
            ->assertDontSee('Inaktiver Eintrag')
            ->assertDontSee('Fremdkunden-Eintrag');
    }

    public function test_show_renders_form_fields_and_hides_invisible_items(): void {
        $visible = $this->item(['form_template_id' => $this->formTemplate()->id]);
        $intern = $this->item(['name' => 'Nur intern', 'visibility' => null]);

        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $restricted = $this->item([
            'name' => 'Für andere',
            'visibility' => ['portal' => true, 'customer_ids' => [(int) $otherCustomer->id]],
        ]);

        $this->actingAsPortalUser()
            ->get(route('customer.catalog.show', $visible))
            ->assertOk()
            ->assertSee('Gewünschte CPU')
            ->assertSee('Betriebssystem');

        $this->actingAsPortalUser()->get(route('customer.catalog.show', $intern))->assertNotFound();
        $this->actingAsPortalUser()->get(route('customer.catalog.show', $restricted))->assertNotFound();
        $this->actingAsPortalUser()
            ->post(route('customer.catalog.order', $restricted), [])
            ->assertNotFound();
    }

    public function test_order_freezes_snapshots_and_creates_portal_ticket(): void {
        $item = $this->item([
            'form_template_id' => $this->formTemplate()->id,
            'approval_chain' => [['approver' => ['type' => 'role', 'value' => 'teamleitung']]],
        ]);

        $this->actingAsPortalUser()
            ->post(route('customer.catalog.order', $item), [
                'values' => ['cpu' => 'i7', 'os' => 'Linux'],
            ])
            ->assertRedirect();

        $request = ServiceRequest::query()->firstOrFail();
        $ticket = $request->ticket()->firstOrFail();

        // Snapshots eingefroren.
        $this->assertSame(['cpu' => 'i7', 'os' => 'Linux'], $request->form_snapshot['answers']);
        $this->assertSame('Notebook bestellen', $request->catalog_snapshot['name']);
        $this->assertSame(1, $request->catalog_snapshot['version']);
        $this->assertSame(ServiceRequest::STATUS_PENDING, $request->status);

        // Katalogänderung schreibt NIE um.
        $item->update(['name' => 'Umbenannt', 'version' => 7]);
        $this->assertSame('Notebook bestellen', $request->fresh()->catalog_snapshot['name']);

        // Portal-Kontext des Tickets.
        $this->assertSame('customer_portal', $ticket->source->value);
        $this->assertSame('service_request', $ticket->kind->value);
        $this->assertSame((int) $this->portalQueue->id, (int) $ticket->queue_id);
        $this->assertSame((int) $this->customer->id, (int) $ticket->customer_id);
        $this->assertSame((int) $this->portalUser->id, (int) $ticket->requester_id);
    }

    public function test_order_enforces_required_form_fields(): void {
        $item = $this->item(['form_template_id' => $this->formTemplate()->id]);

        $this->actingAsPortalUser()
            ->post(route('customer.catalog.order', $item), ['values' => ['os' => 'Linux']])
            ->assertSessionHasErrors('values.cpu');

        $this->assertSame(0, ServiceRequest::query()->count());
    }

    public function test_request_status_is_visible_in_portal(): void {
        $item = $this->item([
            'approval_chain' => [['approver' => ['type' => 'role', 'value' => 'teamleitung']]],
        ]);

        $this->actingAsPortalUser()
            ->post(route('customer.catalog.order', $item), [])
            ->assertRedirect();

        $this->actingAsPortalUser()
            ->get(route('customer.catalog.index'))
            ->assertOk()
            ->assertSee('Notebook bestellen')
            ->assertSee('Wartet auf Genehmigung');
    }

    public function test_foreign_customers_requests_are_not_listed(): void {
        $item = $this->item([
            'approval_chain' => [['approver' => ['type' => 'role', 'value' => 'teamleitung']]],
        ]);
        $this->actingAsPortalUser()->post(route('customer.catalog.order', $item), [])->assertRedirect();

        // Zweiter Kunde derselben Org sieht die Bestellung des ersten nicht.
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($otherCustomer);
        $otherPortalUser = User::factory()
            ->kunde((int) $otherCustomer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);

        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorSetup::class);
        $this->actingAs($otherPortalUser, 'customer')
            ->get(route('customer.catalog.index'))
            ->assertOk()
            ->assertDontSee('Wartet auf Genehmigung');
    }
}
