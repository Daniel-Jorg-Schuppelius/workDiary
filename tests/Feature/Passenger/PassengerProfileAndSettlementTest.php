<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PassengerProfileAndSettlementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Passenger;

use App\Models\Passenger\PassengerShiftSettlement;
use App\Models\{Classification, ProcedureTemplate, Qualification, Tag};
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Passenger\PassengerRideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-456 — Branchenprofil `taxi-mietwagen` (installierbar, idempotent,
 * P-Schein-Seed) und Schichtabrechnung (getrennte Umsatzarten, Differenz
 * bleibt offen bis zur Klärung).
 */
class PassengerProfileAndSettlementTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_branch_profile_installs_idempotently_with_qualification_seed(): void {
        $installer = app(BranchProfileInstaller::class);
        $actor = $this->orgAdmin();

        $installer->install($this->organization, 'taxi-mietwagen', $actor);

        // Klassifikationen der Kern-Domänen vorhanden.
        $this->assertTrue(Classification::query()
            ->where('organization_id', $this->organization->id)
            ->where('domain', 'permit_type')->where('code', 'taxikonzession')->exists());
        $this->assertTrue(Classification::query()
            ->where('organization_id', $this->organization->id)
            ->where('domain', 'product_group')->where('code', 'taxifahrt')->exists());

        // P-Schein-Qualifikation exakt so benannt wie der Dispositions-Guard sie prüft.
        $this->assertTrue(Qualification::query()
            ->where('organization_id', $this->organization->id)
            ->where('name', PassengerRideService::DRIVER_QUALIFICATION)->exists());

        // TX-Prozeduren angelegt.
        $this->assertTrue(ProcedureTemplate::query()
            ->where('organization_id', $this->organization->id)
            ->where('code', 'TX_DISPOSITION')->exists());

        // Reinstallation erzeugt keine Duplikate.
        $classificationCount = Classification::query()->where('organization_id', $this->organization->id)->count();
        $tagCount = Tag::query()->where('organization_id', $this->organization->id)->count();
        $installer->install($this->organization, 'taxi-mietwagen', $actor);
        $this->assertSame($classificationCount, Classification::query()->where('organization_id', $this->organization->id)->count());
        $this->assertSame($tagCount, Tag::query()->where('organization_id', $this->organization->id)->count());

        // Profilkennung an der Organisation verankert (Kontext-Gates).
        $settings = (array) $this->organization->refresh()->settings;
        $this->assertSame('taxi-mietwagen', $settings['branch_profile_code'] ?? null);
    }

    public function test_shift_settlement_separates_revenue_kinds_and_keeps_difference_open(): void {
        $driver = $this->orgUser();
        $settlement = PassengerShiftSettlement::query()->create([
            'organization_id' => $this->organization->id,
            'driver_user_id' => $driver->id,
            'shift_date' => now()->toDateString(),
            'meter_total' => '540.00',
            'cash_total' => '260.00',
            'card_total' => '200.00',
            'voucher_total' => '20.00',
            'invoice_total' => '30.00',
            'mediator_total' => '10.00',
            'tip_total' => '25.00',
            'cancelled_total' => '0.00',
        ]);

        // 540 − (260+200+20+30+10) = 20 offen; Trinkgeld zählt NICHT dagegen.
        $this->assertSame('520.00', $settlement->paymentTotal());
        $this->assertSame('20.00', $settlement->computeDifference());
        $this->assertFalse($settlement->isBalanced());

        // Nach Klärung (Storno einer Fahrt über 20 €) ist die Schicht glatt.
        $settlement->update(['cancelled_total' => '20.00']);
        $this->assertTrue($settlement->refresh()->isBalanced());
    }
}
