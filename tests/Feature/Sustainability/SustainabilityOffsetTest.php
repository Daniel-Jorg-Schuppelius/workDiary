<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityOffsetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sustainability;

use App\Models\Platform\User;
use App\Models\Sustainability\{SustainabilityOffset, SustainabilityReportSnapshot};
use App\Services\Sustainability\SustainabilityClaimChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-961: Klimanachweise getrennt von Emissionen, Prüfung von Umweltaussagen. */
final class SustainabilityOffsetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_offsets_are_recorded_and_flagged_without_evidence(): void {
        $this->actingAs($this->admin)->post(route('sustainability.offsets.store'), ['kind' => 'compensation', 'provider' => 'Klimaprojekt eG', 'standard' => 'Gold Standard', 'quantity_t' => '12.5', 'claim_year' => 2026])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('sustainability.offsets.store'), ['kind' => 'compensation', 'provider' => 'Registerprojekt', 'quantity_t' => '3', 'claim_year' => 2026, 'retired_on' => '2026-06-30', 'registry_reference' => 'GS-1-2-3'])->assertSessionHas('success');
        [$open, $retired] = SustainabilityOffset::query()->orderBy('id')->get()->all();
        $this->assertFalse($open->isEvidenced());
        $this->assertTrue($retired->isEvidenced());

        $this->actingAs($this->admin)->get(route('sustainability.offsets.index'))->assertOk()
            ->assertSeeText('Klimaprojekt eG')->assertSeeText(__('sustainability.offset.not_evidenced'))->assertSeeText('15,5 t');
        $this->actingAs($this->admin)->delete(route('sustainability.offsets.destroy', $open))->assertSessionHas('success');
        $this->assertSame(1, SustainabilityOffset::query()->count());
    }

    public function test_claim_checker_flags_neutrality_and_generic_claims(): void {
        $checker = app(SustainabilityClaimChecker::class);
        $reasons = array_column($checker->check('Wir liefern klimaneutral und umweltfreundlich — 100 % Ökostrom.'), 'reason');
        $this->assertSame(['offset_neutrality', 'generic', 'evidence'], $reasons);
        $this->assertSame([], $checker->check('Wir haben den Stromverbrauch 2026 um 12 % gesenkt.'));

        $this->actingAs($this->admin)->from(route('sustainability.offsets.index'))->post(route('sustainability.claims.check'), ['claim_text' => 'CO₂-neutraler Versand'])->assertRedirect(route('sustainability.offsets.index'));
        $this->actingAs($this->admin)->get(route('sustainability.offsets.index'))->assertSeeText(__('sustainability.claim.reason.offset_neutrality'));
    }

    public function test_excerpt_statement_is_checked_and_offsets_are_shown_separately(): void {
        $snapshot = SustainabilityReportSnapshot::query()->create(['organization_id' => $this->organization->id, 'period_start' => '2026-01-01', 'period_end' => '2026-06-30', 'data' => ['co2e_total_kg' => 5000.0], 'created_by' => $this->admin->id]);
        SustainabilityOffset::query()->create(['organization_id' => $this->organization->id, 'kind' => 'green_energy', 'provider' => 'Stadtwerke', 'quantity_t' => '2', 'claim_year' => 2026]);
        $this->actingAs($this->admin)->post(route('sustainability.excerpt.rotate'));
        $token = session('sustainability_excerpt_token');
        $this->actingAs($this->admin)->patch(route('sustainability.excerpt.toggle'), ['enabled' => 1]);

        $this->actingAs($this->admin)->put(route('sustainability.excerpt.publish'), ['snapshot_id' => $snapshot->sqid, 'statement' => 'Unser Betrieb ist klimaneutral.'])
            ->assertSessionHas('success')->assertSessionHas('warning');
        auth()->logout();
        app()->forgetInstance('currentOrganization');
        $this->get(route('sustainability-excerpt.public', $token))->assertOk()
            ->assertSeeText('Unser Betrieb ist klimaneutral.')->assertSeeText(__('sustainability.offset.public_title'))->assertSeeText('Stadtwerke')->assertSeeText('5,0 t CO₂e');
    }
}
