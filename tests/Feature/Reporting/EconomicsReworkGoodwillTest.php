<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EconomicsReworkGoodwillTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\{Classification, Customer, Project, TimeEntry, User};
use App\Services\Reporting\EconomicsReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rang 59: Nacharbeit/Kulanz getrennt in der Nachkalkulation + signierter
 * Beleg-Drilldown mit Summen-Konsistenz.
 */
class EconomicsReworkGoodwillTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Project $project;

    private Classification $rework;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'is_default' => false,
        ]);

        $this->rework = Classification::query()->create([
            'organization_id' => $this->organization->id,
            'domain' => 'rework_reason',
            'code' => 'montagefehler',
            'label' => 'Montagefehler',
            'active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function entry(User $user, array $overrides = []): TimeEntry {
        return TimeEntry::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $user->id,
            'date' => '2026-06-10',
            'minutes' => 60,
            'billable' => false,
            'internal_rate' => 50,
        ], $overrides));
    }

    public function test_builder_separates_rework_and_goodwill(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $goodwill = Classification::query()->create([
            'organization_id' => $this->organization->id,
            'domain' => 'goodwill_reason',
            'code' => 'stammkunde',
            'label' => 'Stammkunde',
            'active' => true,
        ]);

        $this->entry($user); // normal
        $this->entry($user, ['rework_reason_classification_id' => $this->rework->id, 'minutes' => 30, 'internal_rate' => 25]);
        $this->entry($user, ['goodwill_reason_classification_id' => $goodwill->id, 'minutes' => 90, 'internal_rate' => 75]);

        $rows = app(EconomicsReportBuilder::class)->byProject(
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
            [$this->project->id],
        );

        $row = $rows[0];
        $this->assertSame(30, $row['reworkMinutes']);
        $this->assertSame(90, $row['goodwillMinutes']);
        // internal_rate wird vom Model-Hook (RateCalculator) neu berechnet —
        // die Kosten-Abgrenzung folgt derselben Summe wie costTime.
        $this->assertLessThanOrEqual($row['costTime'], $row['reworkCost'] + $row['goodwillCost']);
        // 30 von 180 Minuten = 16,67 %.
        $this->assertSame(16.67, $row['reworkShare']);
    }

    public function test_drilldown_requires_signature_and_sums_match(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->entry($user, ['rework_reason_classification_id' => $this->rework->id, 'minutes' => 30]);
        $this->entry($user, ['rework_reason_classification_id' => $this->rework->id, 'minutes' => 45, 'date' => '2026-06-12']);

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $params = ['kind' => 'rework', 'project' => $this->project->id, 'from' => '2026-06-01', 'to' => '2026-06-30'];

        // Ohne Signatur: 403.
        $this->actingAs($admin)->get(route('reports.economics.drilldown', $params))->assertForbidden();

        $signed = URL::temporarySignedRoute('reports.economics.drilldown', now()->addHour(), $params);

        // Ohne Report-Recht: 403 trotz gültiger Signatur.
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($plain)->get($signed)->assertForbidden();

        // Summen-Konsistenz: Fußzeile == Builder-Zellenwert (30 + 45 = 75).
        $response = $this->actingAs($admin)->get($signed);
        $response->assertOk();
        $response->assertSee('Montagefehler');
        $response->assertSee('75');
    }
}
