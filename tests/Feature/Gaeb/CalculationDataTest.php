<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalculationDataTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqItemType, GaebPhase};
use App\Models\{BillOfQuantity, BoqCostType, BoqItem, BoqItemCostApproach, User};
use App\Services\Gaeb\{BoqCalculationDataService, BoqExportService, GaebImportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kalkulationsdaten X52 (Feature 109, MVP-647).
 *
 * **Die Kostenart trägt den Zuschlag, der Ansatz die Menge.** Ein Betrieb
 * schlägt nach Art der Kosten zu (Lohn anders als Material), nicht je
 * Position — genau das bildet das Format ab, und genau so wird es gespeichert.
 */
final class CalculationDataTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function xml(): string {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA52/3.3">
          <GAEBInfo><Version>3.3</Version><Date>2026-08-18</Date><ProgSystem>Test</ProgSystem></GAEBInfo>
          <PrjInfo><NamePrj>Neubau Kita</NamePrj><Cur>EUR</Cur></PrjInfo>
          <Award>
            <DP>52</DP>
            <Cur>EUR</Cur>
            <BoQ ID="BOQ-1">
              <BoQInfo>
                <Name>1</Name>
                <BoQBkdn><Type>BoQLevel</Type><Length>3</Length><Num>Yes</Num></BoQBkdn>
                <BoQBkdn><Type>Item</Type><Length>4</Length><Num>Yes</Num></BoQBkdn>
                <CostType Key="LO"><CostDescription><p><span>Lohn</span></p></CostDescription><CostTypeUnit>h</CostTypeUnit><Markup>12.500000</Markup></CostType>
                <CostType Key="MA"><CostDescription><p><span>Material</span></p></CostDescription><CostTypeUnit>kg</CostTypeUnit></CostType>
              </BoQInfo>
              <BoQBody>
                <BoQCtgy RNoPart="001" ID="C-001">
                  <LblTx><p><span>Erdarbeiten</span></p></LblTx>
                  <BoQBody>
                    <Itemlist>
                      <Item RNoPart="0010" ID="I-1">
                        <Qty>100.000</Qty>
                        <QU>m3</QU>
                        <Description><CompleteText><OutlineText><OutlTxt><TextOutlTxt><p><span>Boden loesen</span></p></TextOutlTxt></OutlTxt></OutlineText></CompleteText></Description>
                        <CostApproach Key="LO">
                          <CostApproachQty>2.500</CostApproachQty>
                          <CostApproachQU>h</CostApproachQU>
                          <Performance>1.000</Performance>
                          <Value>48.000</Value>
                        </CostApproach>
                        <CostApproach Key="MA">
                          <CostApproachQty>15.000</CostApproachQty>
                          <Value>1.200</Value>
                        </CostApproach>
                      </Item>
                    </Itemlist>
                  </BoQBody>
                </BoQCtgy>
              </BoQBody>
            </BoQ>
          </Award>
        </GAEB>
        XML;
    }

    private function import(): BillOfQuantity {
        $import = app(GaebImportService::class)->import(
            $this->xml(), 'kalkulation.x52', $this->organization->id, ['created_by' => $this->admin->id]
        );

        return BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);
    }

    /** Kostenarten landen im Kopf, Ansätze an der Position. */
    public function test_import_stores_cost_types_and_approaches(): void {
        $bill = $this->import();

        $types = BoqCostType::query()->where('bill_of_quantity_id', $bill->id)->orderBy('position')->get();
        $this->assertCount(2, $types);
        $this->assertSame('LO', $types[0]->cost_key);
        $this->assertSame('h', $types[0]->unit);
        $this->assertSame('12.500000', $types[0]->markup_percent);
        // Ohne Zuschlag bleibt das Feld leer statt auf 0 gesetzt.
        $this->assertNull($types[1]->markup_percent);

        $item = BoqItem::query()->where('bill_of_quantity_id', $bill->id)->firstOrFail();
        $approaches = $item->costApproaches()->get();
        $this->assertCount(2, $approaches);
        $this->assertSame('LO', $approaches[0]->cost_key);
        $this->assertSame('48.000', $approaches[0]->value);
        // Ohne eigene Einheit gilt die der Kostenart — sie wird nicht kopiert.
        $this->assertNull($approaches[1]->unit);
    }

    /** `KW = Menge × Wert ÷ Leistung`; ohne Leistung steht der Wert für sich. */
    public function test_calculated_amount_follows_the_documented_formula(): void {
        $bill = $this->import();
        $item = BoqItem::query()->where('bill_of_quantity_id', $bill->id)->firstOrFail();
        $approaches = $item->costApproaches()->get();

        // 2,5 h × 48,00 € ÷ 1,0 = 120,00 €
        $this->assertSame(120.0, $approaches[0]->calculatedAmount());
        // 15 kg × 1,20 € (keine Leistung) = 18,00 €
        $this->assertSame(18.0, $approaches[1]->calculatedAmount());
    }

    /** Ohne Menge oder Wert gibt es nichts zu rechnen. */
    public function test_incomplete_approach_has_no_amount(): void {
        $bill = $this->import();
        $item = BoqItem::query()->where('bill_of_quantity_id', $bill->id)->firstOrFail();

        $approach = BoqItemCostApproach::query()->create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'cost_key' => 'GE',
            'quantity' => '3.000',
            'position' => 9,
        ]);

        $this->assertNull($approach->calculatedAmount());
    }

    /** Der Export gibt zurück, was hereinkam. */
    public function test_export_writes_the_calculation_data_back(): void {
        $bill = $this->import();

        $result = app(BoqExportService::class)->export($bill->fresh() ?? $bill, GaebPhase::CalculationData);
        $xml = $result['xml'];

        $this->assertStringContainsString('<CostType Key="LO">', $xml);
        $this->assertStringContainsString('<CostApproach Key="MA">', $xml);
        $this->assertStringContainsString('<Value>48</Value>', $xml);
    }

    /**
     * EKT/GKT-Aggregation (MVP-647): **Der Zuschlag hängt an der Kostenart.**
     *
     * Lohn trägt 12,5 % (120,00 € → 15,00 €), Material keinen Satz. Eine
     * unterstellte Null wäre dasselbe Ergebnis, aber eine andere Aussage: Sie
     * behauptete, der Betrieb schlage auf Material nichts zu.
     */
    public function test_ekt_and_gkt_are_aggregated_per_cost_type(): void {
        $bill = $this->import();

        $report = app(BoqCalculationDataService::class)->forBill($bill);

        $this->assertSame(['LO', 'MA'], array_column($report['byCostType'], 'key'));
        $this->assertSame(120.0, $report['byCostType'][0]['ekt']);
        $this->assertSame(15.0, $report['byCostType'][0]['gkt']);
        $this->assertSame(18.0, $report['byCostType'][1]['ekt']);
        $this->assertSame(0.0, $report['byCostType'][1]['gkt']);

        $this->assertSame(138.0, $report['ekt']);
        $this->assertSame(15.0, $report['gkt']);
        $this->assertSame(153.0, $report['calculated']);
    }

    /**
     * Eine Position ohne Preis wird gezählt, nicht als 0 € verbucht — sonst
     * stammte die Gesamtdifferenz aus fehlenden Preisen und sähe wie ein
     * Kalkulationsfehler aus.
     */
    public function test_unpriced_items_are_counted_not_valued(): void {
        $bill = $this->import();

        $report = app(BoqCalculationDataService::class)->forBill($bill);

        $this->assertSame(1, $report['unpriced']);
        $this->assertSame(0.0, $report['offered']);
        $this->assertNull($report['items'][0]['delta']);
    }

    /** Mit Preis entsteht die Differenz, um die es geht. */
    public function test_delta_compares_calculation_against_the_offered_price(): void {
        $bill = $this->import();
        $item = BoqItem::query()->where('bill_of_quantity_id', $bill->id)->firstOrFail();
        $item->forceFill(['unit_price' => '1.5000', 'total_price' => '150.0000'])->save();

        $report = app(BoqCalculationDataService::class)->forBill($bill->fresh() ?? $bill);

        $this->assertSame(0, $report['unpriced']);
        $this->assertSame(150.0, $report['offered']);
        // 153,00 € kalkuliert gegen 150,00 € angeboten.
        $this->assertSame(3.0, $report['delta']);
    }

    /**
     * X52-Regel: Eine Zuschlagsposition rechnet prozentual auf andere
     * Positionen — eigene Ansätze zählten dasselbe Geld zweimal.
     */
    public function test_markup_items_with_approaches_are_reported(): void {
        $bill = $this->import();
        $item = BoqItem::query()->where('bill_of_quantity_id', $bill->id)->firstOrFail();

        $service = app(BoqCalculationDataService::class);
        $this->assertSame([], $service->markupItemsWithApproaches($bill));

        $item->forceFill(['type' => BoqItemType::Markup->value])->save();
        $this->assertSame([$item->reference_no], $service->markupItemsWithApproaches($bill->fresh() ?? $bill));
    }

    public function test_calculation_page_shows_ekt_and_gkt(): void {
        $bill = $this->import();

        $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.calculation-data', $bill))
            ->assertOk()
            ->assertSee('Lohn')
            ->assertSee('138,00')   // EKT gesamt
            ->assertSee('15,00');   // GKT gesamt
    }

    public function test_calculation_csv_carries_both_blocks(): void {
        $bill = $this->import();

        $response = $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.calculation-data', [$bill, 'export' => 'csv']));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Kostenarten', $body);
        $this->assertStringContainsString('Positionen', $body);
        $this->assertStringContainsString('EKT', $body);
    }

    /** In den LV-Phasen tauchen die Kalkulationsdaten nicht auf. */
    public function test_other_phases_stay_free_of_calculation_data(): void {
        $bill = $this->import();

        $result = app(BoqExportService::class)->export($bill->fresh() ?? $bill, GaebPhase::Award);

        $this->assertStringNotContainsString('CostApproach', $result['xml']);
    }
}
