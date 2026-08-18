<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostEstimateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, GaebPhase};
use App\Models\{BillOfQuantity, BoqCatalog, BoqCatalogAssignment, BoqItem, Project, User};
use App\Models\Costing\CostEstimate;
use App\Services\Gaeb\{CostEstimateService, CostGroupReportService};
use Database\Seeders\CatalogRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kostenermittlung nach DIN 276 / HOAI (Feature 109, MVP-646).
 *
 * Kern der Prüfung: **Das Budget kommt aus der Ermittlung am Projekt** — ohne
 * Projekt bleibt es `null`, nicht 0,00 €. Und erzeugt werden nur die beiden
 * Stufen, für die WorkDiary Daten hält.
 */
final class CostEstimateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Project $project;
    private BillOfQuantity $bill;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        (new CatalogRegistrySeeder)->run();

        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->bill = BillOfQuantity::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'name' => 'Neubau Kita',
            'phase' => GaebPhase::Award,
            'status' => BoqItemStatus::Imported,
            'created_by' => $this->admin->id,
        ]);
    }

    /** Eine knappe, aber vollständige X51 mit zwei Kostengruppen. */
    private function x51(): string {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA51/3.3">
          <GAEBInfo><Version>3.3</Version><Date>2026-08-18</Date><ProgSystem>Test</ProgSystem></GAEBInfo>
          <PrjInfo><NamePrj>Neubau Kita</NamePrj><Cur>EUR</Cur></PrjInfo>
          <ElementalCosting>
            <DP>51</DP>
            <ECInfo>
              <Name>KB 2026</Name>
              <LblEC>Kostenberechnung Neubau Kita</LblEC>
              <ECType>cost determination</ECType>
              <ECMethod>cost by elements</ECMethod>
              <Date>2026-03-01</Date>
              <Cur>EUR</Cur>
            </ECInfo>
            <ECBody>
              <CostElement>
                <EleNo>310</EleNo>
                <Descr>Baugrube, Erdbau</Descr>
                <QU>psch</QU>
                <IT>120000.00</IT>
              </CostElement>
              <CostElement>
                <EleNo>320</EleNo>
                <Descr>Gruendung</Descr>
                <QU>psch</QU>
                <IT>80000.00</IT>
              </CostElement>
            </ECBody>
          </ElementalCosting>
        </GAEB>
        XML;
    }

    private function item(string $reference, string $quantity, string $price, ?string $code = null): BoqItem {
        $item = BoqItem::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'reference_no' => $reference,
            'short_text' => 'Position ' . $reference,
            'quantity' => $quantity,
            'unit' => 'm3',
            'unit_price' => $price,
            'status' => BoqItemStatus::Imported,
        ]);

        if ($code !== null) {
            BoqCatalogAssignment::query()->create([
                'organization_id' => $this->organization->id,
                'bill_of_quantity_id' => $this->bill->id,
                'assignable_type' => $item->getMorphClass(),
                'assignable_id' => $item->id,
                'catalog_key' => 'idDIN276-18',
                'code' => $code,
                'source' => 'import',
            ]);
        }

        return $item;
    }

    private function catalog(): BoqCatalog {
        return BoqCatalog::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'catalog_key' => 'idDIN276-18',
            'type' => 'cost group DIN 276 2018-12',
            'name' => 'DIN 276-18',
        ]);
    }

    public function test_x51_import_creates_the_estimate(): void {
        $estimate = app(CostEstimateService::class)
            ->import($this->x51(), $this->organization->id, $this->admin, $this->project);

        $this->assertSame(CostEstimate::STAGE_CALCULATION, $estimate->stage);
        $this->assertSame('x51_import', $estimate->source);
        $this->assertSame('2026-03-01', $estimate->determined_on->toDateString());
        $this->assertSame(2, $estimate->items()->count());
        $this->assertSame('310', $estimate->items()->orderBy('position')->first()?->code);
    }

    /** Das Budget aus der Ermittlung erscheint in der Kostenverfolgung. */
    public function test_budget_feeds_the_lifecycle(): void {
        $this->catalog();
        $this->item('01.0010', '100.000', '1000.00', '310');
        app(CostEstimateService::class)->import($this->x51(), $this->organization->id, $this->admin, $this->project);

        $lifecycle = app(CostGroupReportService::class)->lifecycle($this->bill, null, 2);
        $row = collect($lifecycle['rows'])->firstWhere('code', '310');

        $this->assertNotNull($lifecycle['estimate']);
        $this->assertSame(120000.0, $row['budget']);
        $this->assertSame(100000.0, $row['boq']);
        // Die Budgetsumme kommt aus der Ermittlung, nicht aus den LV-Zeilen.
        $this->assertSame(200000.0, $lifecycle['totals']['budget']);
    }

    /** Ohne Projekt gibt es kein Budget — `null`, nicht 0,00 €. */
    public function test_without_a_project_there_is_no_budget(): void {
        $this->bill->update(['project_id' => null]);
        $this->catalog();
        $this->item('01.0010', '10.000', '10.00', '310');
        app(CostEstimateService::class)->import($this->x51(), $this->organization->id, $this->admin, $this->project);

        $lifecycle = app(CostGroupReportService::class)->lifecycle($this->bill->fresh() ?? $this->bill, null, 2);

        $this->assertNull($lifecycle['estimate']);
        $this->assertNull($lifecycle['rows'][0]['budget']);
        $this->assertNull($lifecycle['totals']['budget']);
    }

    /**
     * Eine selbst abgeleitete Ermittlung ist kein Budget, sondern ihr
     * Gegenstück — sonst verglichen sich die eigenen Zahlen mit sich selbst.
     */
    public function test_derived_estimates_are_not_used_as_budget(): void {
        $this->catalog();
        $this->item('01.0010', '10.000', '10.00', '310');
        app(CostEstimateService::class)->deriveFromBill($this->bill, CostEstimate::STAGE_QUOTE, $this->admin);

        $lifecycle = app(CostGroupReportService::class)->lifecycle($this->bill, null, 2);

        $this->assertNull($lifecycle['estimate']);
    }

    /** Der Kostenanschlag nimmt den vergebenen Stand, die Feststellung den aufgemessenen. */
    public function test_derivation_uses_the_right_figures_per_stage(): void {
        $this->catalog();
        $item = $this->item('01.0010', '100.000', '10.00', '310');
        app(\App\Services\Gaeb\BoqProgressService::class)->record($item, '60.000');

        $quote = app(CostEstimateService::class)->deriveFromBill($this->bill->fresh() ?? $this->bill, CostEstimate::STAGE_QUOTE, $this->admin);
        $final = app(CostEstimateService::class)->deriveFromBill($this->bill->fresh() ?? $this->bill, CostEstimate::STAGE_FINAL, $this->admin);

        $this->assertSame('1000.00', $quote->items()->first()?->amount);
        $this->assertSame('600.00', $final->items()->first()?->amount);
    }

    /**
     * Der Export übersteht den Round-Trip: gelesen wird zurück, was
     * geschrieben wurde.
     *
     * **Keine Schemaprüfung** — die Kostenschemata stapeln zwei
     * `xs:redefine`-Stufen, die libxml nicht auflöst; der Validator sagt das
     * ausdrücklich, statt einen Folgefehler als Dokumentfehler auszugeben. Der
     * Round-Trip ist hier ohnehin der schärfere Nachweis: Er prüft die Zahlen,
     * nicht nur die Form.
     */
    public function test_export_survives_a_round_trip(): void {
        $this->catalog();
        $this->item('01.0010', '100.000', '10.00', '310');
        $estimate = app(CostEstimateService::class)->deriveFromBill($this->bill, CostEstimate::STAGE_QUOTE, $this->admin);

        $xml = app(CostEstimateService::class)->export($estimate);
        $this->assertStringContainsString('ElementalCosting', $xml);
        $this->assertStringContainsString('<DP>51</DP>', $xml);

        $again = (new \ERechnungToolkit\Parsers\GaebCostingParser)->parse($xml);
        $elements = $again->getElements();

        $this->assertCount(1, $elements);
        $this->assertSame('310', $elements[0]->getNumber());
        $this->assertSame(1000.0, $elements[0]->getTotal()?->toFloat());
        $this->assertSame(\ERechnungToolkit\Enums\GaebCostingType::Quote, $again->getType());
    }

    public function test_export_route_delivers_the_file(): void {
        $this->catalog();
        $this->item('01.0010', '100.000', '10.00', '310');

        $response = $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.cost-estimate.export', [$this->bill, 'stage' => 'quote']));

        $response->assertOk();
        $this->assertStringContainsString('.x51', (string) $response->headers->get('Content-Disposition'));
    }

    /** Schätzung und Berechnung erzeugt WorkDiary nicht — dafür fehlen die Daten. */
    public function test_only_two_stages_can_be_exported(): void {
        $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.cost-estimate.export', [$this->bill, 'stage' => 'estimate']))
            ->assertStatus(422);
    }

    public function test_import_route_stores_the_estimate(): void {
        $this->actingAs($this->admin)->post(route('bill-of-quantities.cost-estimate.import'), [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('kb.x51', $this->x51()),
            'project' => $this->project->sqid,
        ])->assertRedirect();

        $this->assertSame(1, CostEstimate::query()->count());
        $this->assertSame($this->project->id, CostEstimate::query()->first()?->project_id);
    }
}
