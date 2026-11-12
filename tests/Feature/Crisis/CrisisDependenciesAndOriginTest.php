<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisDependenciesAndOriginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Crisis;

use App\Enums\Crisis\CrisisActionStatus;
use App\Enums\User\Permission;
use App\Models\Asset\Asset;
use App\Models\Crisis\{CrisisAction, CrisisCase};
use App\Models\Platform\User;
use App\Models\Safety\SafetyEvent;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1605: Abhängigkeiten zwischen Maßnahmen, Ersatzprozess und Reststatus,
 * Absprung zur Notfall-Anwesenheitsliste, Krise aus einem Vorfall eröffnen
 * und Verknüpfen über eine Auswahl.
 */
final class CrisisDependenciesAndOriginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function crisis(string $status = 'activated'): CrisisCase {
        return CrisisCase::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Serverraum überflutet',
            'category' => 'infrastructure',
            'severity' => 'major',
            'status' => $status,
            'activated_at' => $status === 'activated' ? now() : null,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_action_waits_for_its_predecessor(): void {
        $case = $this->crisis();
        $this->actingAs($this->admin)->post(route('crisis.actions.store', $case), ['title' => 'Strom abschalten', 'priority' => 'high'])
            ->assertSessionHasNoErrors();
        $first = CrisisAction::query()->sole();

        $this->actingAs($this->admin)->post(route('crisis.actions.store', $case), [
            'title' => 'Wasser abpumpen',
            'priority' => 'high',
            'depends_on_id' => $first->sqid,
        ])->assertSessionHasNoErrors();
        $second = CrisisAction::query()->where('title', 'Wasser abpumpen')->sole();
        $this->assertSame($first->id, $second->depends_on_id);

        $this->actingAs($this->admin)->get(route('crisis.show', $case))
            ->assertOk()
            ->assertSee(__('crisis.action.after', ['title' => 'Strom abschalten']));

        $this->actingAs($this->admin)->put(route('crisis.actions.update', [$case, $second]), ['status' => 'done'])
            ->assertSessionHas('error', __('crisis.action.blocked_by', ['title' => 'Strom abschalten']));
        $this->assertSame(CrisisActionStatus::Open, $second->fresh()->status);

        // In Arbeit nehmen bleibt möglich; erledigt erst nach dem Vorgänger.
        $this->actingAs($this->admin)->put(route('crisis.actions.update', [$case, $second]), ['status' => 'in_progress'])->assertSessionHas('status');
        $this->actingAs($this->admin)->put(route('crisis.actions.update', [$case, $first]), ['status' => 'done'])->assertSessionHas('status');
        $this->actingAs($this->admin)->put(route('crisis.actions.update', [$case, $second]), ['status' => 'done'])->assertSessionHas('status');
        $this->assertSame(CrisisActionStatus::Done, $second->fresh()->status);
    }

    public function test_predecessor_can_be_changed_later_without_cycles(): void {
        $case = $this->crisis();
        $make = fn (string $title): CrisisAction => $case->actions()->create(['organization_id' => $this->organization->id, 'title' => $title, 'priority' => 'high', 'status' => 'open']);
        $a = $make('Strom abschalten');
        $b = $make('Wasser abpumpen');
        $c = $make('Trocknen');

        $this->actingAs($this->admin)->get(route('crisis.show', $case))->assertOk()->assertSee(route('crisis.actions.predecessor', [$case, $c]), false);

        $this->actingAs($this->admin)->put(route('crisis.actions.predecessor', [$case, $b]), ['depends_on_id' => $a->sqid])->assertSessionHas('status');
        $this->actingAs($this->admin)->put(route('crisis.actions.predecessor', [$case, $c]), ['depends_on_id' => $b->sqid])->assertSessionHas('status');
        $this->assertSame($b->id, $c->fresh()->depends_on_id);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $c->id, 'event' => 'updated']);

        // A nach C hieße A → C → B → A.
        $this->actingAs($this->admin)->put(route('crisis.actions.predecessor', [$case, $a]), ['depends_on_id' => $c->sqid])
            ->assertSessionHasErrors(['depends_on_id' => __('crisis.action.depends_cycle')]);
        $this->actingAs($this->admin)->put(route('crisis.actions.predecessor', [$case, $a]), ['depends_on_id' => $a->sqid])
            ->assertSessionHasErrors('depends_on_id');
        $this->assertNull($a->fresh()->depends_on_id);

        // Leer löst den Vorgänger; danach ist C ohne Wartebedingung erledigbar.
        $this->actingAs($this->admin)->put(route('crisis.actions.predecessor', [$case, $c]), ['depends_on_id' => ''])->assertSessionHas('status');
        $this->assertNull($c->fresh()->depends_on_id);
        $this->actingAs($this->admin)->put(route('crisis.actions.update', [$case, $c]), ['status' => 'done'])->assertSessionHas('status');

        $foreign = $this->crisis()->actions()->create(['organization_id' => $this->organization->id, 'title' => 'Fremd', 'priority' => 'low', 'status' => 'open']);
        $this->actingAs($this->admin)->put(route('crisis.actions.predecessor', [$case, $b]), ['depends_on_id' => $foreign->sqid])
            ->assertSessionHasErrors(['depends_on_id' => __('crisis.action.depends_foreign')]);
    }

    public function test_predecessor_must_belong_to_the_same_case(): void {
        $case = $this->crisis();
        $other = $this->crisis();
        $foreign = $other->actions()->create(['organization_id' => $this->organization->id, 'title' => 'Fremd', 'priority' => 'low', 'status' => 'open']);

        $this->actingAs($this->admin)->post(route('crisis.actions.store', $case), [
            'title' => 'Abhängig',
            'priority' => 'low',
            'depends_on_id' => $foreign->sqid,
        ])->assertSessionHasErrors('depends_on_id');
        $this->assertSame(0, $case->actions()->count());
    }

    public function test_continuity_impact_takes_substitute_process_and_residual_note(): void {
        $case = $this->crisis();

        $this->actingAs($this->admin)->post(route('crisis.bcm.store', $case), [
            'process_name' => 'Auftragsannahme',
            'rto_hours' => 4,
            'substitute_process' => 'Annahme per Telefon und Papierformular',
        ])->assertSessionHasNoErrors();
        $impact = $case->continuityImpacts()->sole();
        $this->assertSame('Annahme per Telefon und Papierformular', $impact->substitute_process);

        $this->actingAs($this->admin)->put(route('crisis.bcm.update', [$case, $impact]), [
            'status' => 'degraded',
            'residual_note' => 'Nur zwei Arbeitsplätze verfügbar',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nur zwei Arbeitsplätze verfügbar', $impact->fresh()->residual_note);

        $this->actingAs($this->admin)->get(route('crisis.show', $case))
            ->assertOk()
            ->assertSee('Annahme per Telefon und Papierformular')
            ->assertSee('Nur zwei Arbeitsplätze verfügbar');
    }

    public function test_active_case_links_to_the_emergency_attendance_list_only_with_its_right(): void {
        $case = $this->crisis();

        $this->actingAs($this->admin)->get(route('crisis.show', $case))
            ->assertOk()
            ->assertSee(route('reports.presence-emergency'));

        $staff = User::factory()->create(['organization_id' => $this->organization->id]);
        $staff->givePermissionTo([Permission::CrisisViewAny->value, Permission::CrisisView->value]);
        $this->actingAs($staff)->get(route('crisis.show', $case))
            ->assertOk()
            ->assertDontSee(route('reports.presence-emergency'));
    }

    public function test_crisis_opens_from_a_safety_event_and_links_it(): void {
        $event = SafetyEvent::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('safety-events.show', $event))
            ->assertOk()
            ->assertSee(__('crisis.open.action'));

        $this->actingAs($this->admin)->get(route('crisis.create', ['from' => 'safety_event', 'source' => $event->sqid]))
            ->assertOk()
            ->assertSee('name="origin_sqid" value="' . $event->sqid . '"', false);

        $this->actingAs($this->admin)->post(route('crisis.store'), [
            'title' => 'Brand in Halle 2',
            'category' => 'safety',
            'severity' => 'critical',
            'origin_type' => 'safety_event',
            'origin_sqid' => $event->sqid,
        ])->assertSessionHasNoErrors();

        $case = CrisisCase::query()->where('title', 'Brand in Halle 2')->sole();
        $link = $case->links()->sole();
        $this->assertTrue(MorphMap::is($link->linkable_type, SafetyEvent::class));
        $this->assertSame($event->id, (int) $link->linkable_id);
    }

    public function test_unknown_origin_opens_no_case(): void {
        $this->actingAs($this->admin)->post(route('crisis.store'), [
            'title' => 'Ohne Vorfall',
            'category' => 'safety',
            'severity' => 'minor',
            'origin_type' => 'safety_event',
            'origin_sqid' => 'gibtesnicht',
        ])->assertSessionHas('error');
        $this->assertSame(0, CrisisCase::query()->count());

        $this->actingAs($this->admin)->post(route('crisis.store'), [
            'title' => 'Kein Vorfallstyp',
            'category' => 'safety',
            'severity' => 'minor',
            'origin_type' => 'customer',
            'origin_sqid' => 'x',
        ])->assertSessionHasErrors('origin_type');
    }

    public function test_link_dialog_offers_a_selection_instead_of_a_sqid_field(): void {
        $case = $this->crisis();
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Notstromaggregat']);

        $this->actingAs($this->admin)->get(route('crisis.show', $case))
            ->assertOk()
            ->assertDontSee('name="linkable_sqid"', false)
            ->assertSee(route('crisis.links.create', $case));

        $this->actingAs($this->admin)->get(route('crisis.links.create', $case))
            ->assertOk()
            ->assertSee('Notstromaggregat')
            ->assertSee('asset:' . $asset->sqid, false);

        $this->actingAs($this->admin)->post(route('crisis.links.store', $case), ['link_target' => 'asset:' . $asset->sqid, 'note' => 'versorgt den Serverraum'])
            ->assertSessionHas('status');
        $link = $case->links()->sole();
        $this->assertTrue(MorphMap::is($link->linkable_type, Asset::class));

        $this->actingAs($this->admin)->get(route('crisis.show', $case))
            ->assertOk()
            ->assertSee('Notstromaggregat')
            ->assertSee(route('assets.show', $asset));
    }
}
