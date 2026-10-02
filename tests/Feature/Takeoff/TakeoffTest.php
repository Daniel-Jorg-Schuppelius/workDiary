<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Takeoff;

use App\Enums\Gaeb\{BoqItemStatus, GaebPhase};
use App\Enums\Takeoff\TakeoffFormula;
use App\Models\Diary\DiaryEntry;
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Models\Platform\User;
use App\Models\Takeoff\Takeoff;
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Gaeb\BoqExportService;
use App\Services\Takeoff\TakeoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1058: Aufmaßblatt — REB-Formeln über das Toolkit, Sperre nach Abschluss, PDF, X31. */
class TakeoffTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Aufmaß Test']);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_formulas_are_computed_by_the_reb_calculator(): void {
        $service = app(TakeoffService::class);

        $this->assertSame('11.0500', $service->quantityOf(TakeoffFormula::Rectangle, ['4,25', '2,60']));
        $this->assertSame('27.6250', $service->quantityOf(TakeoffFormula::Rectangle, ['4,25', '2,60', '2,5']));
        $this->assertSame('-1.7688', $service->quantityOf(TakeoffFormula::Rectangle, ['0,88', '2,01'], '-1'));
        $this->assertSame('9.2812', $service->quantityOf(TakeoffFormula::Free, ['4,25*2,60-0,88*2,01']));
        $this->assertSame('3.1416', $service->quantityOf(TakeoffFormula::Circle, ['1', '400']));
        $this->assertSame('7.5000', $service->quantityOf(TakeoffFormula::Sum, ['5', '3,5', '-1']));
        $this->assertNull($service->quantityOf(TakeoffFormula::Rectangle, ['4,25']));
        // Ein vertippter Wert zählt nicht still als 0.
        $this->assertNull($service->quantityOf(TakeoffFormula::Rectangle, ['4,25', 'abc']));
        $this->assertNull($service->quantityOf(TakeoffFormula::Rectangle, ['4,25', '2'], 'x'));
    }

    public function test_takeoff_on_an_order_with_lines_totals_lock_and_pdf(): void {
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'title' => 'Wohnzimmer streichen']);

        $this->post(route('takeoffs.store'), ['carrier_type' => 'diary', 'carrier_id' => $entry->sqid, 'title' => 'Wohnzimmer'])->assertRedirect();
        $takeoff = Takeoff::query()->firstOrFail();
        $this->assertSame($entry->id, $takeoff->diary_entry_id);

        $this->post(route('takeoffs.lines.store', $takeoff), ['formula' => '04', 'values' => ['4,25', '2,60'], 'factor' => '2', 'label' => 'Wand Nord/Süd', 'description' => 'Wandfläche', 'unit' => 'm2'])->assertRedirect();
        $this->post(route('takeoffs.lines.store', $takeoff), ['formula' => '04', 'values' => ['0,88', '2,01'], 'factor' => '-1', 'label' => 'Tür', 'description' => 'Wandfläche', 'unit' => 'm2'])->assertRedirect();

        $totals = app(TakeoffService::class)->totals($takeoff->fresh());
        $this->assertCount(1, $totals);
        $this->assertSame('20.3312', $totals[0]['quantity']);

        $this->post(route('takeoffs.transition', $takeoff), ['status' => 'completed'])->assertRedirect();
        $this->post(route('takeoffs.lines.store', $takeoff), ['formula' => '00', 'values' => ['1']])->assertForbidden();

        $this->get(route('takeoffs.show', $takeoff))->assertOk()->assertSee('20,331');
        $response = $this->get(route('takeoffs.pdf', $takeoff));
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_completed_takeoff_lines_go_into_the_x31_export(): void {
        $bill = BillOfQuantity::factory()->create(['organization_id' => $this->organization->id, 'status' => BoqItemStatus::Ordered->value]);
        $item = BoqItem::factory()->create(['organization_id' => $this->organization->id, 'bill_of_quantity_id' => $bill->id, 'reference_no' => '01.0010', 'unit' => 'm2']);

        $this->post(route('takeoffs.store'), ['carrier_type' => 'boq', 'carrier_id' => $bill->sqid, 'title' => 'Aufmaß EG'])->assertRedirect();
        $takeoff = Takeoff::query()->firstOrFail();
        $this->post(route('takeoffs.lines.store', $takeoff), ['formula' => '04', 'values' => ['10', '2,5'], 'boq_item_id' => $item->sqid])->assertRedirect();
        $this->post(route('takeoffs.transition', $takeoff), ['status' => 'completed']);

        $result = app(BoqExportService::class)->export($bill->fresh(), GaebPhase::QuantitySurvey, $this->admin->id);

        $this->assertMatchesRegularExpression('/<QTakeoff Row="[^"]*10000[^"]*2500[^"]*"/', $result['xml']);
    }

    public function test_trade_presets_from_the_profile_offer_quick_lines(): void {
        app(BranchProfileInstaller::class)->install($this->organization, 'maler', $this->admin);
        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->admin->id]);
        $this->post(route('takeoffs.store'), ['carrier_type' => 'diary', 'carrier_id' => $entry->sqid, 'title' => 'Flur'])->assertRedirect();
        $takeoff = Takeoff::query()->firstOrFail();

        $this->get(route('takeoffs.show', $takeoff))->assertOk()->assertSee('Öffnung abziehen');
        $this->get(route('takeoffs.lines.create', [$takeoff, 'formula' => '04', 'description' => 'Öffnung abziehen', 'unit' => 'm2', 'factor' => '-1']))
            ->assertOk()->assertSee('value="-1"', false);
    }
}
