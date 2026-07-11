<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationsReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Applications;

use App\Models\Applications\ApplicationOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 068, MVP-188/194/198: Bericht liefert Pipeline/Trefferquote/
 * Verlustgründe + Bewerber-/Vertragskennzahlen — nur für Berechtigte,
 * inkl. CSV-Export.
 */
final class ApplicationsReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_report_renders_with_aggregates_and_csv_export(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Gewonnen',
            'kind' => 'tender',
            'status' => 'won',
            'estimated_value' => '10000',
            'created_by' => $admin->id,
        ]);
        ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Verloren',
            'kind' => 'tender',
            'status' => 'lost',
            'loss_reason' => 'Preis zu hoch',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('applications.report'))
            ->assertOk()
            ->assertViewHas('tenders', fn(array $tenders): bool => $tenders['win_rate'] === 50.0
                && isset($tenders['loss_reasons']['Preis zu hoch']));

        $csv = $this->actingAs($admin)->get(route('applications.report', ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('TREFFERQUOTE', (string) $csv->getContent());
    }

    public function test_report_denies_unauthorized_users(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('applications.report'))->assertForbidden();
    }
}
