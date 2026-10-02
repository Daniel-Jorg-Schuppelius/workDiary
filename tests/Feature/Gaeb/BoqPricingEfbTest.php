<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqPricingEfbTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, BoqItemType};
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Models\Platform\User;
use App\Services\Gaeb\EfbPriceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1056: LV bepreisen (EP aus der EP-Aufgliederung, „nicht angeboten“,
 * Sperre nach der Abgabe) und EFB-Preisblätter 221/223.
 */
class BoqPricingEfbTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private BillOfQuantity $bill;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['name' => 'Bau Test']);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->bill = BillOfQuantity::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => BoqItemStatus::Imported->value,
            'up_components' => [
                ['no' => 1, 'label' => 'Lohn', 'category' => 'Wages'],
                ['no' => 2, 'label' => 'Stoffe', 'category' => 'Materials'],
                ['no' => 3, 'label' => 'Geräte', 'category' => 'Plant'],
                ['no' => 4, 'label' => 'Sonstiges', 'category' => 'Miscellaneous'],
            ],
        ]);
        foreach ([['01.0010', '12.5000'], ['01.0020', '3.0000']] as $i => [$ref, $qty]) {
            BoqItem::factory()->create([
                'organization_id' => $this->organization->id,
                'bill_of_quantity_id' => $this->bill->id,
                'reference_no' => $ref,
                'quantity' => $qty,
                'unit' => 'm2',
                'unit_price' => null,
                'position' => $i + 1,
            ]);
        }
        BoqItem::factory()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'reference_no' => '01.0030',
            'type' => BoqItemType::Note->value,
            'unit_price' => null,
            'position' => 3,
        ]);

        $this->put(route('articles.calculation-scheme.update'), [
            'average_wage_amount' => '20.00',
            'wage_related_percent' => '80',
            'wage_ancillary_amount' => '4.00',
            'markups' => collect(['labour', 'material', 'equipment', 'other', 'subcontract'])
                ->mapWithKeys(fn (string $k): array => [$k => ['site_overhead_percent' => '10', 'general_overhead_percent' => '8', 'risk_profit_percent' => '2']])
                ->all(),
        ])->assertRedirect();
    }

    private function item(string $reference): BoqItem {
        return $this->bill->items()->where('reference_no', $reference)->firstOrFail();
    }

    public function test_unit_price_is_the_sum_of_the_breakdown_and_total_follows(): void {
        $first = $this->item('01.0010');
        $second = $this->item('01.0020');

        $this->put(route('bill-of-quantities.pricing.update', $this->bill), ['rows' => [
            $first->sqid => ['components' => ['24.00', '10.50', '2.00', ''], 'unit_price' => '999'],
            $second->sqid => ['not_offered' => '1', 'unit_price' => '5'],
        ]])->assertRedirect(route('bill-of-quantities.pricing', $this->bill));

        $first->refresh();
        $this->assertSame('36.5000', $first->unit_price?->getAmount());
        $this->assertSame(['24.00', '10.50', '2.00', '0'], $first->unit_price_components);
        $this->assertSame('456.25', $first->total_price?->withScale(2)->getAmount());
        $second->refresh();
        $this->assertTrue($second->not_offered);
        $this->assertNull($second->unit_price);
    }

    public function test_pricing_is_locked_after_the_offer_was_submitted(): void {
        $this->bill->update(['status' => BoqItemStatus::Quoted->value]);
        $item = $this->item('01.0010');

        $this->put(route('bill-of-quantities.pricing.update', $this->bill), ['rows' => [
            $item->sqid => ['unit_price' => '10'],
        ]])->assertSessionHas('error');

        $this->assertNull($item->fresh()->unit_price);
    }

    public function test_efb_221_and_223_values_and_pdfs(): void {
        $this->put(route('bill-of-quantities.pricing.update', $this->bill), ['rows' => [
            $this->item('01.0010')->sqid => ['components' => ['24.00', '10.50', '2.00', '0']],
        ]]);
        $sheets = app(EfbPriceSheetService::class);

        $form221 = $sheets->form221($this->organization->fresh());
        // ML 20,00; LGK 80 % = 16,00; LNK 4,00 → KL 40,00; Zuschlag 20 % → VL 48,00.
        $this->assertSame('40.00', $form221['calculationWage']->getAmount());
        $this->assertSame('48.00', $form221['billingWage']->getAmount());

        $form223 = $sheets->form223($this->bill->fresh(), $form221['billingWage']);
        $this->assertCount(2, $form223['rows']);
        $this->assertSame(0.5, $form223['rows'][0]['hours']);
        $this->assertSame('10.50', $form223['rows'][0]['material']?->getAmount());
        $this->assertSame(1, $form223['missing']);

        foreach (['221', '223'] as $form) {
            $response = $this->get(route('bill-of-quantities.efb', [$this->bill, $form]));
            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        }
        $this->get(route('bill-of-quantities.pricing', $this->bill))->assertOk()->assertSee('01.0010');
    }
}
