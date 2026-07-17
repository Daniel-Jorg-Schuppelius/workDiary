<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsRiskTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\RiskStatus;
use App\Models\Isms\{IsmsControl, IsmsRisk};
use App\Models\{Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsmsRiskTest extends TestCase {
    use RefreshDatabase;

    public function test_admin_can_create_risk_with_computed_score_and_running_number(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('isms.risks.index'))
            ->post(route('isms.risks.store'), [
                'title' => 'Ransomware auf Fileserver',
                'description' => 'Verschlüsselung der Ablage durch Schadsoftware.',
                'category' => 'technical',
                'asset_ref' => 'Fileserver FS-01',
                'threat' => 'Schadsoftware über Phishing-Anhang',
                'likelihood' => 4,
                'impact' => 5,
                'treatment' => 'mitigate',
                'review_due_on' => now()->addMonths(6)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_risks', [
            'title' => 'Ransomware auf Fileserver',
            'organization_id' => $admin->organization_id,
            'risk_no' => 1,
            'score' => 20,
            'status' => RiskStatus::Identified->value,
        ]);

        // Zweites Risiko bekommt die nächste laufende Nummer.
        $this->actingAs($admin)
            ->post(route('isms.risks.store'), [
                'title' => 'Stromausfall Serverraum',
                'category' => 'physical',
                'likelihood' => 2,
                'impact' => 4,
                'treatment' => 'transfer',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('isms_risks', ['title' => 'Stromausfall Serverraum', 'risk_no' => 2, 'score' => 8]);
    }

    public function test_update_recomputes_score(): void {
        $admin = User::factory()->admin()->create();
        $risk = $this->makeRisk($admin, ['likelihood' => 2, 'impact' => 2, 'score' => 4]);

        $this->actingAs($admin)
            ->put(route('isms.risks.update', $risk), [
                'title' => $risk->title,
                'category' => $risk->category->value,
                'likelihood' => 5,
                'impact' => 5,
                'treatment' => $risk->treatment->value,
            ])
            ->assertRedirect();

        $this->assertSame(25, $risk->refresh()->score);
    }

    public function test_status_transition_follows_state_machine(): void {
        $admin = User::factory()->admin()->create();
        $risk = $this->makeRisk($admin);

        // identified → analyzed ist erlaubt …
        $this->actingAs($admin)
            ->post(route('isms.risks.transition', $risk), ['status' => 'analyzed'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(RiskStatus::Analyzed, $risk->refresh()->status);

        // … identified → treated wäre es nicht gewesen: analyzed → identified gibt es nicht.
        $this->actingAs($admin)
            ->from(route('isms.risks.index'))
            ->post(route('isms.risks.transition', $risk), ['status' => 'identified'])
            ->assertRedirect()
            ->assertSessionHasErrors('status');
        $this->assertSame(RiskStatus::Analyzed, $risk->refresh()->status);

        // analyzed → accepted ist erlaubt — erfordert seit 046-D aber eine
        // freigegebene Netto-Bewertung mit Ablauf-/Reviewdatum.
        \App\Models\Isms\IsmsRiskAssessment::factory()->net()->approved($admin->id)->create([
            'organization_id' => $admin->organization_id,
            'isms_risk_id' => $risk->id,
            'valid_until' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->post(route('isms.risks.transition', $risk), ['status' => 'accepted'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(RiskStatus::Accepted, $risk->refresh()->status);
    }

    public function test_risk_can_be_linked_to_controls(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $controlA = IsmsControl::factory()->create(['organization_id' => $admin->organization_id]);
        $controlB = IsmsControl::factory()->create(['organization_id' => $admin->organization_id]);
        $risk = $this->makeRisk($admin);

        $this->actingAs($admin)
            ->put(route('isms.risks.update', $risk), [
                'title' => $risk->title,
                'category' => $risk->category->value,
                'likelihood' => $risk->likelihood,
                'impact' => $risk->impact,
                'treatment' => $risk->treatment->value,
                'control_ids' => ['', $controlA->sqid, $controlB->sqid],
            ])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$controlA->id, $controlB->id],
            $risk->refresh()->controls->pluck('id')->all(),
        );

        // Abwählen über den leeren Marker löst alle Verknüpfungen.
        $this->actingAs($admin)
            ->put(route('isms.risks.update', $risk), [
                'title' => $risk->title,
                'category' => $risk->category->value,
                'likelihood' => $risk->likelihood,
                'impact' => $risk->impact,
                'treatment' => $risk->treatment->value,
                'control_ids' => [''],
            ])
            ->assertRedirect();

        $this->assertCount(0, $risk->refresh()->controls);
    }

    public function test_controls_of_other_organization_cannot_be_linked(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-other']);
        $foreignControl = IsmsControl::factory()->create(['organization_id' => $otherOrg->id]);

        $risk = $this->makeRisk($admin);

        $this->actingAs($admin)
            ->put(route('isms.risks.update', $risk), [
                'title' => $risk->title,
                'category' => $risk->category->value,
                'likelihood' => $risk->likelihood,
                'impact' => $risk->impact,
                'treatment' => $risk->treatment->value,
                'control_ids' => [(string) $foreignControl->id],
            ])
            ->assertRedirect();

        $this->assertCount(0, $risk->refresh()->controls, 'Fremde Controls dürfen nicht verknüpft werden');
    }

    public function test_index_shows_matrix_and_risks(): void {
        $admin = User::factory()->admin()->create();
        $this->makeRisk($admin, ['title' => 'Matrix-Testrisiko', 'likelihood' => 5, 'impact' => 5, 'score' => 25]);

        $this->actingAs($admin)
            ->get(route('isms.risks.index'))
            ->assertOk()
            ->assertSee('Matrix-Testrisiko')
            ->assertSee(__('isms.matrix.title'))
            ->assertViewHas('matrix', function (array $matrix): bool {
                return ($matrix[5][5] ?? 0) === 1 && ($matrix[1][1] ?? null) === 0;
            });
    }

    public function test_regular_user_cannot_access_or_manage_risks(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('isms.risks.index'))->assertForbidden();

        $this->actingAs($user)
            ->post(route('isms.risks.store'), [
                'title' => 'Verboten',
                'category' => 'technical',
                'likelihood' => 1,
                'impact' => 1,
                'treatment' => 'accept',
            ])
            ->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_but_not_manage(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();

        $this->actingAs($gf)->get(route('isms.risks.index'))->assertOk();

        $this->actingAs($gf)
            ->post(route('isms.risks.store'), [
                'title' => 'Nur lesen',
                'category' => 'technical',
                'likelihood' => 1,
                'impact' => 1,
                'treatment' => 'accept',
            ])
            ->assertForbidden();
    }

    public function test_cross_organization_risk_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreignRisk = $this->makeRisk($otherAdmin);

        $this->actingAs($admin)
            ->put(route('isms.risks.update', $foreignRisk), [
                'title' => 'Hijack',
                'category' => 'technical',
                'likelihood' => 1,
                'impact' => 1,
                'treatment' => 'accept',
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijack', $foreignRisk->refresh()->title);
    }

    private function makeRisk(User $owner, array $overrides = []): IsmsRisk {
        app()->instance('currentOrganization', $owner->organization);

        return IsmsRisk::factory()->create([
            'organization_id' => $owner->organization_id,
            ...$overrides,
        ]);
    }
}
