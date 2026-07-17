<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsControlTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\ControlImplementationStatus;
use App\Models\Isms\{IsmsControl, IsmsRequirement, IsmsRisk};
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Normneutrale Maßnahmen (Feature 046): CRUD, Anforderungs-Mapping
 * (n:m, auch normübergreifend), org-sicheres Sync, Risiko-Verknüpfung,
 * SoA-Dialog + Print-View.
 */
class IsmsControlTest extends TestCase {
    use RefreshDatabase;

    public function test_admin_can_create_control(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.controls.index'))
            ->post(route('isms.controls.store'), [
                'title' => 'Offboarding-Checkliste IT',
                'description' => 'Rückgabe von Geräten und Entzug aller Zugänge.',
                'implementation_status' => 'partial',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_controls', [
            'title' => 'Offboarding-Checkliste IT',
            'organization_id' => $admin->organization_id,
            'implementation_status' => ControlImplementationStatus::Partial->value,
        ]);
    }

    public function test_control_can_be_mapped_to_multiple_requirements_across_norms(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $catalogA = IsmsRequirement::factory()->catalog('A.5.1')->create(['organization_id' => $admin->organization_id]);
        $catalogB = IsmsRequirement::factory()->catalog('A.8.4', 'Zugriff auf Quellcode')->create(['organization_id' => $admin->organization_id]);
        $custom = IsmsRequirement::factory()->create([
            'organization_id' => $admin->organization_id,
            'ref_no' => 'M-07',
        ]);

        $this->actingAs($admin)
            ->post(route('isms.controls.store'), [
                'title' => 'Berechtigungskonzept pflegen',
                'implementation_status' => 'open',
                'requirement_ids' => ['', $catalogA->sqid, $catalogB->sqid, $custom->sqid],
            ])
            ->assertRedirect();

        /** @var IsmsControl $control */
        $control = IsmsControl::query()->where('title', 'Berechtigungskonzept pflegen')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$catalogA->id, $catalogB->id, $custom->id],
            $control->requirements->pluck('id')->all(),
            'Eine Maßnahme erfüllt mehrere Anforderungen — auch normübergreifend',
        );

        // Abwählen über den leeren Marker löst alle Mappings.
        $this->actingAs($admin)
            ->put(route('isms.controls.update', $control), [
                'title' => $control->title,
                'implementation_status' => 'open',
                'requirement_ids' => [''],
            ])
            ->assertRedirect();

        $this->assertCount(0, $control->refresh()->requirements);
    }

    public function test_requirements_of_other_organization_cannot_be_mapped(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-req-foreign']);
        $foreignRequirement = IsmsRequirement::factory()->create(['organization_id' => $otherOrg->id]);

        app()->instance('currentOrganization', $admin->organization);

        $this->actingAs($admin)
            ->post(route('isms.controls.store'), [
                'title' => 'Mapping-Schmuggel',
                'implementation_status' => 'open',
                'requirement_ids' => [(string) $foreignRequirement->id],
            ])
            ->assertRedirect();

        /** @var IsmsControl $control */
        $control = IsmsControl::query()->where('title', 'Mapping-Schmuggel')->firstOrFail();
        $this->assertCount(0, $control->requirements, 'Fremde Anforderungen dürfen nicht gemappt werden');
    }

    public function test_control_risk_link_remains_intact(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $control = IsmsControl::factory()->create(['organization_id' => $admin->organization_id]);
        $risk = IsmsRisk::factory()->create(['organization_id' => $admin->organization_id]);

        $this->actingAs($admin)
            ->put(route('isms.risks.update', $risk), [
                'title' => $risk->title,
                'category' => $risk->category->value,
                'likelihood' => $risk->likelihood,
                'impact' => $risk->impact,
                'treatment' => $risk->treatment->value,
                'control_ids' => [(string) $control->id],
            ])
            ->assertRedirect();

        $this->assertSame([$control->id], $risk->refresh()->controls->pluck('id')->all());

        // Löschen der Maßnahme löst die Verknüpfung.
        $this->actingAs($admin)->delete(route('isms.controls.destroy', $control))->assertRedirect();
        $this->assertCount(0, $risk->refresh()->controls);
    }

    public function test_soa_page_renders_statements_with_controls_and_risks(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        // Eigene Anforderung M-07 über den Controller (legt Default-Scope
        // + Statement automatisch an).
        $this->actingAs($admin)
            ->post(route('isms.requirements.store'), [
                'norm' => 'Eigene',
                'edition' => '-',
                'ref_no' => 'M-07',
                'title' => 'Notfallvorsorge dokumentieren',
            ])
            ->assertRedirect();

        /** @var IsmsRequirement $requirement */
        $requirement = IsmsRequirement::query()->where('ref_no', 'M-07')->firstOrFail();

        $control = IsmsControl::factory()->create([
            'organization_id' => $admin->organization_id,
            'title' => 'Notfallhandbuch pflegen',
        ]);
        $control->requirements()->attach($requirement->id);

        $risk = IsmsRisk::factory()->create([
            'organization_id' => $admin->organization_id,
            'risk_no' => 12,
        ]);
        $control->risks()->attach($risk->id);

        // Standard: Dialog-Partial (entry-modal).
        $this->actingAs($admin)
            ->get(route('isms.soa'))
            ->assertOk()
            ->assertSee(__('isms.title.soa'))
            ->assertSee('M-07')
            ->assertSee('Notfallhandbuch pflegen')
            ->assertSee('R-12');

        // ?print=1: druckbare Standalone-Ansicht bleibt erhalten.
        $this->actingAs($admin)
            ->get(route('isms.soa', ['print' => 1]))
            ->assertOk()
            ->assertSee(__('isms.soa.heading'))
            ->assertSee('M-07')
            ->assertSee('Notfallhandbuch pflegen')
            ->assertSee('R-12');
    }

    public function test_regular_user_cannot_access_controls(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('isms.controls.index'))->assertForbidden();
        $this->actingAs($user)->get(route('isms.soa'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('isms.controls.store'), [
                'title' => 'Verboten',
                'implementation_status' => 'open',
            ])
            ->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_but_not_manage(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();

        $this->actingAs($gf)->get(route('isms.controls.index'))->assertOk();
        $this->actingAs($gf)->get(route('isms.soa'))->assertOk();
        $this->actingAs($gf)
            ->post(route('isms.controls.store'), [
                'title' => 'Nur lesen',
                'implementation_status' => 'open',
            ])
            ->assertForbidden();
    }

    public function test_cross_organization_control_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-ctrl-cross']);
        $foreign = IsmsControl::factory()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($admin)
            ->put(route('isms.controls.update', $foreign), [
                'title' => 'Hijack',
                'implementation_status' => 'open',
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijack', $foreign->refresh()->title);
    }
}
