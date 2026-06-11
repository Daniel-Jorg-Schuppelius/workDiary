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

use App\Enums\Isms\{ControlImplementationStatus, ControlSource};
use App\Models\Isms\IsmsControl;
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsmsControlTest extends TestCase {
    use RefreshDatabase;

    public function test_admin_can_create_custom_control(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.controls.index'))
            ->post(route('isms.controls.store'), [
                'code' => 'M-01',
                'title' => 'Offboarding-Checkliste IT',
                'description' => 'Rückgabe von Geräten und Entzug aller Zugänge.',
                'applicable' => '1',
                'implementation_status' => 'partial',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_controls', [
            'code' => 'M-01',
            'organization_id' => $admin->organization_id,
            'source' => ControlSource::Custom->value,
            'applicable' => true,
            'implementation_status' => ControlImplementationStatus::Partial->value,
        ]);
    }

    public function test_not_applicable_without_justification_is_rejected(): void {
        $admin = User::factory()->admin()->create();
        $control = $this->makeControl($admin);

        $this->actingAs($admin)
            ->from(route('isms.controls.index'))
            ->put(route('isms.controls.update', $control), [
                'code' => $control->code,
                'title' => $control->title,
                'applicable' => '0',
                'justification' => '',
                'implementation_status' => 'open',
            ])
            ->assertRedirect(route('isms.controls.index'))
            ->assertSessionHasErrors('justification');

        $this->assertTrue($control->refresh()->applicable, 'Control bleibt unverändert anwendbar');
    }

    public function test_not_applicable_with_justification_forces_status_not_applicable(): void {
        $admin = User::factory()->admin()->create();
        $control = $this->makeControl($admin);

        $this->actingAs($admin)
            ->put(route('isms.controls.update', $control), [
                'code' => $control->code,
                'title' => $control->title,
                'applicable' => '0',
                'justification' => 'Kein eigener Quellcode — Entwicklung ist vollständig ausgelagert.',
                'implementation_status' => 'open',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $control->refresh();
        $this->assertFalse($control->applicable);
        $this->assertSame(ControlImplementationStatus::NotApplicable, $control->implementation_status);
        $this->assertNotNull($control->justification);
    }

    public function test_reactivating_control_resets_not_applicable_status(): void {
        $admin = User::factory()->admin()->create();
        $control = $this->makeControl($admin, notApplicable: true);

        $this->actingAs($admin)
            ->put(route('isms.controls.update', $control), [
                'code' => $control->code,
                'title' => $control->title,
                'applicable' => '1',
                'justification' => $control->justification,
                'implementation_status' => 'notApplicable',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $control->refresh();
        $this->assertTrue($control->applicable);
        $this->assertSame(ControlImplementationStatus::Open, $control->implementation_status);
    }

    public function test_catalog_import_is_idempotent(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('isms.controls.import'))->assertRedirect();
        $this->actingAs($admin)->post(route('isms.controls.import'))->assertRedirect();

        app()->instance('currentOrganization', $admin->organization);
        $this->assertSame(93, IsmsControl::query()->count(), 'Doppelter Import legt keine Duplikate an');
        $this->assertSame(
            93,
            IsmsControl::query()->where('source', ControlSource::Iso27001AnnexA->value)->count(),
        );
        $this->assertDatabaseHas('isms_controls', [
            'organization_id' => $admin->organization_id,
            'code' => 'A.5.1',
            'applicable' => true,
        ]);
        $this->assertDatabaseHas('isms_controls', [
            'organization_id' => $admin->organization_id,
            'code' => 'A.8.34',
        ]);
    }

    public function test_catalog_import_does_not_overwrite_maintained_soa_fields(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('isms.controls.import'))->assertRedirect();

        app()->instance('currentOrganization', $admin->organization);
        /** @var IsmsControl $control */
        $control = IsmsControl::query()->where('code', 'A.8.4')->firstOrFail();
        $this->actingAs($admin)
            ->put(route('isms.controls.update', $control), [
                'title' => $control->title,
                'applicable' => '0',
                'justification' => 'Kein Zugriff auf Quellcode nötig.',
                'implementation_status' => 'open',
            ])
            ->assertRedirect();

        $this->actingAs($admin)->post(route('isms.controls.import'))->assertRedirect();

        $control->refresh();
        $this->assertFalse($control->applicable, 'Re-Import überschreibt gepflegte SoA-Aussage nicht');
        $this->assertSame('Kein Zugriff auf Quellcode nötig.', $control->justification);
    }

    public function test_soa_page_renders_controls_with_linked_risks(): void {
        $admin = User::factory()->admin()->create();
        $control = $this->makeControl($admin, ['code' => 'M-07', 'title' => 'Notfallhandbuch pflegen']);
        $risk = \App\Models\Isms\IsmsRisk::factory()->create([
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
            ->assertSee('R-12');
    }

    public function test_regular_user_cannot_access_controls_or_import(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('isms.controls.index'))->assertForbidden();
        $this->actingAs($user)->get(route('isms.soa'))->assertForbidden();
        $this->actingAs($user)->post(route('isms.controls.import'))->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_soa_but_not_import(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();

        $this->actingAs($gf)->get(route('isms.controls.index'))->assertOk();
        $this->actingAs($gf)->get(route('isms.soa'))->assertOk();
        $this->actingAs($gf)->post(route('isms.controls.import'))->assertForbidden();
    }

    public function test_cross_organization_control_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-ctrl-cross']);
        $foreign = IsmsControl::factory()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($admin)
            ->put(route('isms.controls.update', $foreign), [
                'code' => $foreign->code,
                'title' => 'Hijack',
                'applicable' => '1',
                'implementation_status' => 'open',
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijack', $foreign->refresh()->title);
    }

    private function makeControl(User $owner, array $overrides = [], bool $notApplicable = false): IsmsControl {
        app()->instance('currentOrganization', $owner->organization);
        $factory = IsmsControl::factory();
        if ($notApplicable) {
            $factory = $factory->notApplicable();
        }

        return $factory->create([
            'organization_id' => $owner->organization_id,
            ...$overrides,
        ]);
    }
}
