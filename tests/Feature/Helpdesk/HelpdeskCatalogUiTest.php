<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskCatalogUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\User\Permission;
use App\Models\{BusinessService, FormTemplate, Organization, ProcedureTemplate, RequestItem, ServiceOffering, ServiceRequest, ServiceTicket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, MVP-154: Katalog-Pflege-UI — Modal-CRUD über alle drei
 * Ebenen (Fachdienst → Angebot → Katalogeintrag), strukturierte
 * Genehmigungskette (Sqid-dekodiert), Policy-403 ohne
 * service_catalog.manage, Org-404 für fremde Ressourcen.
 */
final class HelpdeskCatalogUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $manager;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->manager = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function service(string $name = 'Arbeitsplatz'): BusinessService {
        return BusinessService::query()->create(['organization_id' => $this->organization->id, 'name' => $name]);
    }

    private function offering(BusinessService $service, string $name = 'Hardware'): ServiceOffering {
        return ServiceOffering::query()->create([
            'organization_id' => $this->organization->id,
            'business_service_id' => $service->id,
            'name' => $name,
        ]);
    }

    private function item(ServiceOffering $offering, string $name = 'Notebook bestellen'): RequestItem {
        return RequestItem::query()->create([
            'organization_id' => $this->organization->id,
            'service_offering_id' => $offering->id,
            'name' => $name,
        ]);
    }

    public function test_index_groups_three_levels_and_hides_foreign_org(): void {
        $offering = $this->offering($this->service('Eigenservice'), 'Eigenangebot');
        $this->item($offering, 'Eigeneintrag');

        $foreignOrg = Organization::factory()->create();
        BusinessService::query()->create(['organization_id' => $foreignOrg->id, 'name' => 'Fremdservice']);

        $this->actingAs($this->manager)
            ->get(route('servicedesk.catalog.index'))
            ->assertOk()
            ->assertSeeInOrder(['Eigenservice', 'Eigenangebot', 'Eigeneintrag'])
            ->assertDontSee('Fremdservice');
    }

    public function test_service_modal_crud(): void {
        $this->actingAs($this->manager)
            ->get(route('servicedesk.catalog.services.create'))
            ->assertOk();

        $this->actingAs($this->manager)
            ->post(route('servicedesk.catalog.services.store'), [
                'name' => 'Facility',
                'description' => 'Gebäudedienste',
                'active' => 1,
            ])
            ->assertRedirect(route('servicedesk.catalog.index'));

        $service = BusinessService::query()->where('name', 'Facility')->firstOrFail();

        $this->actingAs($this->manager)
            ->get(route('servicedesk.catalog.services.edit', $service))
            ->assertOk();

        $this->actingAs($this->manager)
            ->patch(route('servicedesk.catalog.services.update', $service), [
                'name' => 'Facility Management',
                'active' => 1,
            ])
            ->assertRedirect(route('servicedesk.catalog.index'));
        $this->assertSame('Facility Management', $service->fresh()->name);

        // Mit Angeboten nicht löschbar (kein stilles Kaskadieren).
        $this->offering($service);
        $this->actingAs($this->manager)
            ->delete(route('servicedesk.catalog.services.destroy', $service))
            ->assertSessionHas('error');
        $this->assertNotNull(BusinessService::query()->find($service->id));

        $empty = $this->service('Leerdienst');
        $this->actingAs($this->manager)
            ->delete(route('servicedesk.catalog.services.destroy', $empty))
            ->assertSessionHas('success');
        $this->assertNull(BusinessService::query()->find($empty->id));
    }

    public function test_offering_modal_crud(): void {
        $service = $this->service();

        $this->actingAs($this->manager)
            ->post(route('servicedesk.catalog.offerings.store'), [
                'business_service_id' => $service->sqid,
                'name' => 'Software',
                'active' => 1,
            ])
            ->assertRedirect(route('servicedesk.catalog.index'));

        $offering = ServiceOffering::query()->where('name', 'Software')->firstOrFail();
        $this->assertSame((int) $service->id, (int) $offering->business_service_id);

        $this->actingAs($this->manager)
            ->patch(route('servicedesk.catalog.offerings.update', $offering), [
                'business_service_id' => $service->sqid,
                'name' => 'Software & Lizenzen',
                'active' => 1,
            ])
            ->assertRedirect(route('servicedesk.catalog.index'));
        $this->assertSame('Software & Lizenzen', $offering->fresh()->name);

        // Mit Katalogeinträgen nicht löschbar.
        $this->item($offering);
        $this->actingAs($this->manager)
            ->delete(route('servicedesk.catalog.offerings.destroy', $offering))
            ->assertSessionHas('error');
        $this->assertNotNull(ServiceOffering::query()->find($offering->id));
    }

    public function test_item_store_decodes_structured_chain_and_procedure_config(): void {
        $offering = $this->offering($this->service());
        $approver = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $template = FormTemplate::factory()->active()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->manager->id,
        ]);
        $procedure = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->manager)
            ->post(route('servicedesk.catalog.items.store'), [
                'service_offering_id' => $offering->sqid,
                'name' => 'VPN-Zugang',
                'form_template_id' => $template->sqid,
                'fulfillment' => 'procedure',
                'procedure_template_id' => $procedure->sqid,
                'approval_steps' => [
                    ['type' => 'user', 'user' => $approver->sqid],
                    ['type' => 'role', 'role' => 'teamleitung'],
                ],
                'visibility_portal' => 1,
                'visibility_roles' => ['teamleitung'],
                'active' => 1,
            ])
            ->assertRedirect(route('servicedesk.catalog.index'));

        $item = RequestItem::query()->where('name', 'VPN-Zugang')->firstOrFail();
        // Kette strukturiert mit DEKODIERTER User-Id (kein Sqid, kein Roh-JSON).
        $this->assertSame([
            ['approver' => ['type' => 'user', 'value' => (int) $approver->id]],
            ['approver' => ['type' => 'role', 'value' => 'teamleitung']],
        ], $item->approval_chain);
        $this->assertSame((int) $procedure->id, (int) $item->fulfillment_config['procedure_template_id']);
        $this->assertSame((int) $template->id, (int) $item->form_template_id);
        $this->assertTrue((bool) ($item->visibility['portal'] ?? false));
        $this->assertSame(['teamleitung'], $item->visibility['roles']);
    }

    public function test_item_update_bumps_version_and_rejects_foreign_references(): void {
        $offering = $this->offering($this->service());
        $item = $this->item($offering);
        $this->assertSame(1, $item->refresh()->version);

        $this->actingAs($this->manager)
            ->patch(route('servicedesk.catalog.items.update', $item), [
                'service_offering_id' => $offering->sqid,
                'name' => 'Notebook (neu)',
                'fulfillment' => 'task',
                'active' => 1,
            ])
            ->assertRedirect(route('servicedesk.catalog.index'));
        $this->assertSame(2, $item->fresh()->version);
        $this->assertSame('Notebook (neu)', $item->fresh()->name);

        // Fremde Referenz (Angebot einer anderen Org) wird abgelehnt.
        $foreignOrg = Organization::factory()->create();
        $foreignService = BusinessService::query()->create(['organization_id' => $foreignOrg->id, 'name' => 'Fremd']);
        $foreignOffering = ServiceOffering::query()->create([
            'organization_id' => $foreignOrg->id,
            'business_service_id' => $foreignService->id,
            'name' => 'Fremdangebot',
        ]);

        $this->actingAs($this->manager)
            ->patch(route('servicedesk.catalog.items.update', $item), [
                'service_offering_id' => $foreignOffering->sqid,
                'name' => 'Hack',
                'fulfillment' => 'task',
                'active' => 1,
            ])
            ->assertSessionHasErrors('service_offering_id');
    }

    public function test_item_destroy_blocked_when_requests_exist(): void {
        $offering = $this->offering($this->service());
        $item = $this->item($offering);
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->organization->id]);
        ServiceRequest::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'request_item_id' => $item->id,
            'catalog_snapshot' => ['name' => $item->name, 'version' => 1],
            'status' => ServiceRequest::STATUS_DONE,
        ]);

        $this->actingAs($this->manager)
            ->delete(route('servicedesk.catalog.items.destroy', $item))
            ->assertSessionHas('error');
        $this->assertNotNull(RequestItem::query()->find($item->id));
    }

    public function test_manage_requires_service_catalog_permission(): void {
        $viewer = User::factory()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(Permission::ServiceTicketView->value);

        // Sicht folgt dem Ticket-Sichtrecht …
        $this->actingAs($viewer)->get(route('servicedesk.catalog.index'))->assertOk();

        // … Pflege braucht service_catalog.manage.
        $this->actingAs($viewer)
            ->post(route('servicedesk.catalog.services.store'), ['name' => 'Verboten'])
            ->assertForbidden();

        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)->get(route('servicedesk.catalog.index'))->assertForbidden();
    }

    public function test_foreign_org_resources_are_not_reachable(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignService = BusinessService::query()->create(['organization_id' => $foreignOrg->id, 'name' => 'Fremd']);
        $foreignOffering = ServiceOffering::query()->create([
            'organization_id' => $foreignOrg->id,
            'business_service_id' => $foreignService->id,
            'name' => 'Fremdangebot',
        ]);
        $foreignItem = RequestItem::query()->create([
            'organization_id' => $foreignOrg->id,
            'service_offering_id' => $foreignOffering->id,
            'name' => 'Fremdeintrag',
        ]);

        $this->actingAs($this->manager)
            ->get(route('servicedesk.catalog.services.edit', $foreignService->sqid))
            ->assertNotFound();
        $this->actingAs($this->manager)
            ->get(route('servicedesk.catalog.items.edit', $foreignItem->sqid))
            ->assertNotFound();
        $this->actingAs($this->manager)
            ->delete(route('servicedesk.catalog.offerings.destroy', $foreignOffering->sqid))
            ->assertNotFound();
    }
}
