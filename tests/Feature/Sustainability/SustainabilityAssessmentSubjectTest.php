<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityAssessmentSubjectTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sustainability;

use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Models\Sustainability\{SustainabilityAssessment, SustainabilityCriterion, SustainabilityMeasure};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1607: Bewertung mit Bezugsobjekt (Art + Objekt, Morph-Alias), Einstieg
 * von der Akte, Maßnahmen je Bewertung und Drilldown bis zum Objekt.
 */
final class SustainabilityAssessmentSubjectTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        SustainabilityCriterion::query()->create([
            'organization_id' => $this->organization->id,
            'dimension' => 'environment',
            'label' => 'Energieverbrauch',
            'weight' => 3,
            'active' => true,
        ]);
    }

    public function test_assessment_is_created_for_a_chosen_object_and_links_to_it(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kompressor Halle 1']);

        $this->actingAs($this->admin)->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee(__('sustainability.subject.assess_action'))
            ->assertSee('subject=asset%3A' . $asset->sqid, false);

        $this->actingAs($this->admin)->get(route('sustainability.assessments.create', ['subject' => 'asset:' . $asset->sqid]))
            ->assertOk()
            ->assertSee('value="asset:' . $asset->sqid . '" selected', false);

        $this->actingAs($this->admin)->post(route('sustainability.assessments.store'), ['subject_ref' => 'asset:' . $asset->sqid])
            ->assertSessionHasNoErrors();

        $assessment = SustainabilityAssessment::query()->sole();
        $this->assertTrue(MorphMap::is($assessment->subject_type, Asset::class));
        $this->assertNotSame(Asset::class, $assessment->subject_type);
        $this->assertSame($asset->id, (int) $assessment->subject_id);
        $this->assertStringContainsString('Kompressor Halle 1', $assessment->subject_label);
        $this->assertSame(1, $assessment->version);

        // Zweite Bewertung desselben Objekts zählt die Version hoch.
        $this->actingAs($this->admin)->post(route('sustainability.assessments.store'), ['subject_ref' => 'asset:' . $asset->sqid]);
        $this->assertSame(2, (int) SustainabilityAssessment::query()->max('version'));

        $this->actingAs($this->admin)->get(route('sustainability.assessments.show', $assessment))
            ->assertOk()
            ->assertSee(route('assets.show', $asset));

        $other = SustainabilityAssessment::query()->create(['organization_id' => $this->organization->id, 'subject_label' => 'Freie Bewertung', 'version' => 1, 'status' => 'draft']);
        $this->actingAs($this->admin)->get(route('sustainability.assessments.index', ['subject' => 'asset:' . $asset->sqid]))
            ->assertOk()
            ->assertViewHas('assessments', fn ($rows): bool => $rows->total() === 2)
            ->assertSee(route('assets.show', $asset))
            ->assertDontSee($other->subject_label);
    }

    public function test_free_assessment_still_needs_a_label_and_unknown_objects_are_rejected(): void {
        $this->actingAs($this->admin)->post(route('sustainability.assessments.store'), [])
            ->assertSessionHasErrors('subject_label');
        $this->actingAs($this->admin)->post(route('sustainability.assessments.store'), ['subject_ref' => 'asset:gibtesnicht'])
            ->assertSessionHasErrors('subject_ref');
        $this->actingAs($this->admin)->post(route('sustainability.assessments.store'), ['subject_ref' => 'user:1'])
            ->assertSessionHasErrors('subject_ref');
        $this->assertSame(0, SustainabilityAssessment::query()->count());

        $this->actingAs($this->admin)->post(route('sustainability.assessments.store'), ['subject_label' => 'Reinigungsprozess'])
            ->assertSessionHasNoErrors();
        $this->assertNull(SustainabilityAssessment::query()->sole()->subject_type);
    }

    public function test_entry_link_shows_on_supplier_and_needs_the_right(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee(__('sustainability.subject.assess_action'));

        $reader = User::factory()->create(['organization_id' => $this->organization->id]);
        $reader->givePermissionTo([\App\Enums\User\Permission::SupplierView->value, \App\Enums\User\Permission::SupplierViewAny->value]);
        $this->actingAs($reader)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertDontSee(__('sustainability.subject.assess_action'));
    }

    public function test_measure_is_assigned_to_an_assessment(): void {
        $assessment = SustainabilityAssessment::query()->create(['organization_id' => $this->organization->id, 'subject_label' => 'Fuhrpark', 'version' => 1, 'status' => 'draft']);

        $this->actingAs($this->admin)->post(route('sustainability.measures.store'), [
            'title' => 'Umstellung auf E-Transporter',
            'effort' => 'high',
            'assessment_id' => $assessment->sqid,
        ])->assertSessionHasNoErrors();

        $measure = SustainabilityMeasure::query()->sole();
        $this->assertSame($assessment->id, (int) $measure->assessment_id);

        $this->actingAs($this->admin)->get(route('sustainability.assessments.show', $assessment))
            ->assertOk()
            ->assertSee('Umstellung auf E-Transporter');
        $this->actingAs($this->admin)->get(route('sustainability.measures.index'))
            ->assertOk()
            ->assertSee(route('sustainability.assessments.show', $assessment));
    }
}
