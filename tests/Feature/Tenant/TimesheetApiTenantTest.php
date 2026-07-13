<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetApiTenantTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Tenant;

use App\Enums\Project\ProjectStatus;
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{MaterialUsage, Organization, Project, TimeEntry, Timesheet, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cross-Org-Tenant-Tests für die `/api/timesheets/*`-Endpunkte (Bauturbo A17,
 * MVP-335). Quelle: ../WorkDiary-Architecture/security/tenant-audit-2026.md
 * (Folge-Issue 5: "Timesheet-API-Endpunkte brauchen noch eigene Tenant-Tests").
 *
 * Ein Sanctum-Token aus Organisation A — mit den KORREKTEN Abilities
 * (`timesheets:read`/`timesheets:write`, bewusst nicht `*`) — darf Stunden-
 * zettel, Einträge und Materialpositionen der Organisation B weder sehen noch
 * verändern. Konvention des Bestands: Cross-Org-Zugriffe enden als 404
 * (Sqid-Binding + OrganizationScope lösen fremde Records gar nicht erst auf),
 * nicht als 403 — Existenz fremder IDs bleibt damit unbeobachtbar.
 */
class TimesheetApiTenantTest extends TestCase {
    use RefreshDatabase;

    private const MARKER = 'ZZ-TSB-LEAK-KUNDE';

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $userB;

    private Project $projectA;

    private Project $projectB;

    private Timesheet $timesheetA;

    private Timesheet $timesheetB;

    protected function setUp(): void {
        parent::setUp();

        $this->orgA = Organization::factory()->create(['slug' => 'ts-api-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'ts-api-b']);

        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->userB = User::factory()->user()->create(['organization_id' => $this->orgB->id]);

        $this->projectA = $this->withOrg($this->orgA, fn (): Project => Project::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Projekt A',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->adminA->id,
        ]));
        $this->projectB = $this->withOrg($this->orgB, fn (): Project => Project::create([
            'organization_id' => $this->orgB->id,
            'name' => 'Projekt B',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->userB->id,
        ]));

        $this->timesheetA = $this->withOrg($this->orgA, fn (): Timesheet => Timesheet::create([
            'organization_id' => $this->orgA->id,
            'project_id' => $this->projectA->id,
            'user_id' => $this->adminA->id,
            'work_date' => '2030-05-01',
            'status' => TimesheetStatus::Draft->value,
        ]));
        $this->timesheetB = $this->withOrg($this->orgB, fn (): Timesheet => Timesheet::create([
            'organization_id' => $this->orgB->id,
            'project_id' => $this->projectB->id,
            'user_id' => $this->userB->id,
            'work_date' => '2030-05-01',
            'status' => TimesheetStatus::Draft->value,
            'customer_name' => self::MARKER,
        ]));
    }

    private function actAsOrgA(): void {
        Sanctum::actingAs($this->adminA, ['timesheets:read', 'timesheets:write']);
    }

    public function test_index_does_not_leak_cross_org_timesheets(): void {
        $this->actAsOrgA();

        $response = $this->getJson('/api/timesheets');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString(self::MARKER, $body);
        $this->assertStringNotContainsString('"' . $this->timesheetB->sqid . '"', $body);
    }

    public function test_show_cross_org_is_not_found(): void {
        $this->actAsOrgA();

        $this->getJson(route('api.timesheets.show', $this->timesheetB))->assertNotFound();
    }

    public function test_update_cross_org_is_not_found_and_leaves_record_untouched(): void {
        $this->actAsOrgA();

        $this->putJson(route('api.timesheets.update', $this->timesheetB), [
            'work_date' => '2031-01-01',
            'customer_name' => 'HIJACKED-BY-A',
        ])->assertNotFound();

        $this->withOrg($this->orgB, function (): void {
            $fresh = Timesheet::query()->findOrFail($this->timesheetB->id);
            $this->assertSame(self::MARKER, $fresh->customer_name);
        });
    }

    public function test_destroy_cross_org_is_not_found_and_keeps_record(): void {
        $this->actAsOrgA();

        $this->deleteJson(route('api.timesheets.destroy', $this->timesheetB))->assertNotFound();

        $this->withOrg($this->orgB, function (): void {
            $this->assertNotNull(Timesheet::query()->find($this->timesheetB->id));
        });
    }

    public function test_submit_cross_org_is_not_found_and_keeps_status(): void {
        $this->actAsOrgA();

        $this->postJson(route('api.timesheets.submit', $this->timesheetB))->assertNotFound();

        $this->withOrg($this->orgB, function (): void {
            $fresh = Timesheet::query()->findOrFail($this->timesheetB->id);
            $this->assertSame(TimesheetStatus::Draft, $fresh->status);
        });
    }

    public function test_sign_cross_org_is_not_found_and_keeps_unsigned(): void {
        $this->actAsOrgA();

        $this->postJson(route('api.timesheets.sign', $this->timesheetB), [
            'signature' => 'data:image/png;base64,iVBORw0KGgo=',
            'customer_name' => 'Angreifer A',
        ])->assertNotFound();

        $this->withOrg($this->orgB, function (): void {
            $fresh = Timesheet::query()->findOrFail($this->timesheetB->id);
            $this->assertNull($fresh->signed_at);
        });
    }

    public function test_pdf_cross_org_is_not_found(): void {
        $this->actAsOrgA();

        $this->get(route('api.timesheets.pdf', $this->timesheetB))->assertNotFound();
    }

    public function test_store_under_cross_org_project_is_not_found(): void {
        $this->actAsOrgA();

        $this->postJson(route('api.timesheets.store', $this->projectB), [
            'work_date' => '2030-06-01',
        ])->assertNotFound();

        $this->withOrg($this->orgB, function (): void {
            $this->assertSame(1, Timesheet::query()->where('project_id', $this->projectB->id)->count());
        });
    }

    public function test_entries_index_and_store_cross_org_are_not_found(): void {
        $this->actAsOrgA();

        $this->getJson(route('api.timesheets.entries.index', $this->timesheetB))->assertNotFound();
        $this->postJson(route('api.timesheets.entries.store', $this->timesheetB), [
            'minutes' => 60,
        ])->assertNotFound();

        $this->withOrg($this->orgB, function (): void {
            $this->assertSame(0, TimeEntry::query()->where('timesheet_id', $this->timesheetB->id)->count());
        });
    }

    /**
     * Confused-Deputy-Fall: fremder Eintrag (Org B) unter dem EIGENEN
     * Timesheet (Org A) adressiert — das Binding darf den fremden Eintrag
     * nicht auflösen (404), der Eintrag bleibt unverändert bestehen.
     */
    public function test_cross_org_entry_under_own_timesheet_is_not_found(): void {
        $entryB = $this->withOrg($this->orgB, fn (): TimeEntry => TimeEntry::factory()->create([
            'organization_id' => $this->orgB->id,
            'project_id' => $this->projectB->id,
            'user_id' => $this->userB->id,
            'timesheet_id' => $this->timesheetB->id,
            'minutes' => 90,
        ]));

        $this->actAsOrgA();

        $this->putJson(route('api.timesheets.entries.update', [$this->timesheetA, $entryB]), [
            'minutes' => 1,
        ])->assertNotFound();
        $this->deleteJson(route('api.timesheets.entries.destroy', [$this->timesheetA, $entryB]))->assertNotFound();

        $this->withOrg($this->orgB, function () use ($entryB): void {
            $fresh = TimeEntry::query()->findOrFail($entryB->id);
            $this->assertSame(90, (int) $fresh->minutes);
        });
    }

    public function test_materials_index_and_store_cross_org_are_not_found(): void {
        $this->actAsOrgA();

        $this->getJson(route('api.timesheets.materials.index', $this->timesheetB))->assertNotFound();
        $this->postJson(route('api.timesheets.materials.store', $this->timesheetB), [
            'description' => 'Injected',
            'quantity' => 1,
        ])->assertNotFound();

        $this->withOrg($this->orgB, function (): void {
            $this->assertSame(0, MaterialUsage::query()->where('timesheet_id', $this->timesheetB->id)->count());
        });
    }

    public function test_cross_org_material_usage_under_own_timesheet_is_not_found(): void {
        $usageB = $this->withOrg($this->orgB, fn (): MaterialUsage => MaterialUsage::create([
            'organization_id' => $this->orgB->id,
            'timesheet_id' => $this->timesheetB->id,
            'description' => 'Kabel Org B',
            'quantity' => 2,
            'unit' => 'Stk',
            'unit_price' => 5,
        ]));

        $this->actAsOrgA();

        $this->putJson(route('api.timesheets.materials.update', [$this->timesheetA, $usageB]), [
            'description' => 'HIJACKED',
            'quantity' => 1,
        ])->assertNotFound();
        $this->deleteJson(route('api.timesheets.materials.destroy', [$this->timesheetA, $usageB]))->assertNotFound();

        $this->withOrg($this->orgB, function () use ($usageB): void {
            $fresh = MaterialUsage::query()->findOrFail($usageB->id);
            $this->assertSame('Kabel Org B', $fresh->description);
        });
    }

    /**
     * @template T
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function withOrg(Organization $org, \Closure $callback): mixed {
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $org);
        try {
            return $callback();
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
