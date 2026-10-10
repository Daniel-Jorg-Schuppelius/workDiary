<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalPayoutReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\User\{CompensationModel, FlatInterval};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/** Externe Auszahlungen: das Diagramm summiert sich zur Tabelle (MVP-1101). */
class ExternalPayoutReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->orgUser([
            'name' => 'Freie Fritzi',
            'compensation_model' => CompensationModel::Pauschal->value,
            'flat_interval' => FlatInterval::Monatlich->value,
            'flat_amount' => '1000.00',
        ]);
    }

    public function test_monthly_flat_counts_once_per_month_in_weekly_buckets(): void {
        // Ein Monat → Wochen-Buckets; vorher stand die Pauschale in jeder Woche.
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2030, 6))
            ->get(route('reports.external-payouts'))
            ->assertOk();

        $series = $response->viewData('monthlyPayoutSeries');
        $this->assertGreaterThan(1, count($series));
        $this->assertEqualsWithDelta(1000.0, array_sum(array_column($series, 'y')), 0.001);
        $this->assertEqualsWithDelta(1000.0, (float) $response->viewData('total'), 0.001);
    }

    public function test_monthly_flat_counts_per_month_in_quarter_buckets(): void {
        // Drei Jahre → Quartals-Buckets; vorher nur einmal je Quartal statt je Monat.
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-01', '2032-12-31'))
            ->get(route('reports.external-payouts'))
            ->assertOk();

        $series = $response->viewData('monthlyPayoutSeries');
        $this->assertEqualsWithDelta(36000.0, array_sum(array_column($series, 'y')), 0.001);
        $this->assertEqualsWithDelta(3000.0, (float) $series[0]['y'], 0.001);
    }
}
