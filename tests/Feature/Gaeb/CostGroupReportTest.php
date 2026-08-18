<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostGroupReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, GaebPhase};
use App\Models\{BillOfQuantity, BoqCatalog, BoqCatalogAssignment, BoqItem, BoqItemQuantitySplit, BoqSection, User};
use App\Models\Catalog\{CatalogEntry, CatalogRegistry};
use App\Services\Gaeb\CostGroupReportService;
use Database\Seeders\CatalogRegistrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kostengruppen-Auswertung (Feature 109, MVP-637/642).
 *
 * Kern der Prüfung: **Teilmengen schlagen die Position, der Abschnitt vererbt
 * nur an Positionen ohne eigene Angabe, und was ohne Zuordnung bleibt, wird
 * ausgewiesen** — nicht verteilt und nicht verschwiegen.
 */
final class CostGroupReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private BillOfQuantity $bill;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->bill = BillOfQuantity::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Neubau Kita',
            'phase' => GaebPhase::Award,
            'status' => BoqItemStatus::Imported,
            'created_by' => $this->admin->id,
        ]);
    }

    private function catalog(string $type = 'cost group DIN 276 2018-12'): BoqCatalog {
        return BoqCatalog::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'catalog_key' => 'idDIN276-18',
            'type' => $type,
            'name' => 'DIN 276-18',
        ]);
    }

    private function item(string $reference, string $quantity, string $price, ?int $sectionId = null): BoqItem {
        return BoqItem::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'boq_section_id' => $sectionId,
            'reference_no' => $reference,
            'short_text' => 'Position ' . $reference,
            'quantity' => $quantity,
            'unit' => 'm3',
            'unit_price' => $price,
            'status' => BoqItemStatus::Imported,
        ]);
    }

    private function assign(object $target, string $code): void {
        BoqCatalogAssignment::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'assignable_type' => $target::class,
            'assignable_id' => $target->id,
            'catalog_key' => 'idDIN276-18',
            'code' => $code,
            'source' => 'import',
        ]);
    }

    /** Die einfachste Lage: eine Position, eine Kostengruppe. */
    public function test_item_assignment_sums_up(): void {
        $this->catalog();
        $item = $this->item('01.0010', '100.000', '25.00');
        $this->assign($item, '311');

        $report = app(CostGroupReportService::class)->forBill($this->bill, null, 2);

        $this->assertSame(2500.0, $report['total']);
        // Auf der zweiten Ebene wird 311 zu 310 zusammengefasst.
        $this->assertSame('310', $report['rows'][0]['code']);
        $this->assertSame(2500.0, $report['rows'][0]['amount']);
        $this->assertSame(0.0, $report['unassigned']);
    }

    /** Teilmengen schlagen die Position — sonst landet alles in einer Gruppe. */
    public function test_quantity_splits_beat_the_item(): void {
        $this->catalog();
        $item = $this->item('01.0010', '450.000', '10.00');
        // Die Position trüge 310; die Teilmengen sagen es genauer.
        $this->assign($item, '310');

        $first = BoqItemQuantitySplit::query()->create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'quantity' => '300.0000',
            'position' => 1,
        ]);
        $second = BoqItemQuantitySplit::query()->create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'quantity' => '150.0000',
            'position' => 2,
        ]);
        $this->assign($first, '310');
        $this->assign($second, '320');

        $report = app(CostGroupReportService::class)->forBill($this->bill, null, 2);
        $rows = collect($report['rows'])->keyBy('code');

        $this->assertSame(4500.0, $report['total']);
        $this->assertSame(3000.0, $rows['310']['amount']);
        $this->assertSame(1500.0, $rows['320']['amount']);
        $this->assertSame(0.0, $report['unassigned']);
    }

    /** Schöpfen die Teilmengen die Position nicht aus, bleibt ein Rest — sichtbar. */
    public function test_incomplete_splits_leave_a_visible_remainder(): void {
        $this->catalog();
        $item = $this->item('01.0010', '100.000', '10.00');
        $split = BoqItemQuantitySplit::query()->create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'quantity' => '60.0000',
            'position' => 1,
        ]);
        $this->assign($split, '310');

        $report = app(CostGroupReportService::class)->forBill($this->bill, null, 2);

        $this->assertSame(600.0, $report['rows'][0]['amount']);
        $this->assertSame(400.0, $report['unassigned']);
    }

    /** Der Abschnitt vererbt nur, wo die Position selbst nichts sagt. */
    public function test_section_inherits_only_where_the_item_is_silent(): void {
        $this->catalog();
        $section = BoqSection::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->bill->id,
            'reference_no' => '01',
            'label' => 'Erdarbeiten',
            'position' => 1,
        ]);
        $this->assign($section, '310');

        $silent = $this->item('01.0010', '10.000', '10.00', $section->id);
        $loud = $this->item('01.0020', '10.000', '20.00', $section->id);
        $this->assign($loud, '320');

        $report = app(CostGroupReportService::class)->forBill($this->bill, null, 2);
        $rows = collect($report['rows'])->keyBy('code');

        $this->assertSame(100.0, $rows['310']['amount']);
        $this->assertSame(200.0, $rows['320']['amount']);
        $this->assertSame(0.0, $report['unassigned']);
        unset($silent);
    }

    /** Ohne Zuordnung zählt der Betrag nicht als null, sondern als „ohne". */
    public function test_unassigned_amount_is_reported(): void {
        $this->catalog();
        $this->item('01.0010', '10.000', '10.00');

        $report = app(CostGroupReportService::class)->forBill($this->bill);

        $this->assertSame([], $report['rows']);
        $this->assertSame(100.0, $report['unassigned']);
        $this->assertSame(100.0, $report['total']);
    }

    /** Der Katalogstamm liefert die Kurzbezeichnung zur Nummer. */
    public function test_registry_supplies_the_label(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $item = $this->item('01.0010', '10.000', '10.00');
        $this->assign($item, '310');

        $report = app(CostGroupReportService::class)->forBill($this->bill, null, 2);

        $this->assertSame('Baugrube, Erdbau', $report['rows'][0]['label']);
        $this->assertSame('2018-12', $report['registry']?->edition);
    }

    /** Beide DIN-Ausgaben stehen nebeneinander — die 2008er wird nicht migriert. */
    public function test_both_din_editions_are_seeded_side_by_side(): void {
        (new CatalogRegistrySeeder)->run();

        $editions = CatalogRegistry::query()
            ->where('kind', CatalogRegistry::KIND_COST_GROUP)
            ->pluck('edition')->all();

        $this->assertContains('2018-12', $editions);
        $this->assertContains('2008-12', $editions);

        // „310" heißt in beiden Ausgaben etwas anderes.
        $new = CatalogEntry::query()->whereRelation('registry', 'key', 'din276-2018')->where('code', '310')->firstOrFail();
        $old = CatalogEntry::query()->whereRelation('registry', 'key', 'din276-2008')->where('code', '310')->firstOrFail();
        $this->assertSame('Baugrube, Erdbau', $new->label);
        $this->assertSame('Baugrube', $old->label);
    }

    /** Der Seeder darf jeden Deploy laufen. */
    public function test_seeder_is_idempotent(): void {
        (new CatalogRegistrySeeder)->run();
        $first = CatalogEntry::query()->count();
        (new CatalogRegistrySeeder)->run();

        $this->assertSame($first, CatalogEntry::query()->count());
    }

    /**
     * GAEB 90 lässt die Satzarten 70–89 herstellerfrei; manche Systeme legen
     * dort Kostengruppen ab. Geraten wird nichts — aber der Import meldet,
     * dass die Datei etwas trägt, das niemand deutet (MVP-648).
     */
    public function test_gaeb90_vendor_records_are_reported_by_the_import(): void {
        $lines = [
            str_pad('00', 10) . '83' . str_pad('Musterprojekt', 50) . '1122PPPPI90',
            '211111  10 NNN         00000051300m2  ',
            '25Boden loesen',
            '751111  10 KG310',
            '99                                                                   00001',
        ];
        $content = '';
        foreach ($lines as $i => $body) {
            $content .= str_pad(substr($body, 0, 74), 74) . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT) . "\r\n";
        }

        $import = app(\App\Services\Gaeb\GaebImportService::class)
            ->import($content, 'test.d83', $this->organization->id, ['created_by' => $this->admin->id]);

        $warnings = $import->preflight['warnings'] ?? [];
        $this->assertNotEmpty(array_filter(
            $warnings,
            static fn (string $warning): bool => str_contains($warning, '75')
        ), 'Die herstellerfreie Zeilenart wurde nicht gemeldet.');
    }

    // ── Kostenverfolgung (MVP-643) ───────────────────────────────────────

    /**
     * Der Nachtrag zählt getrennt vom LV-Ansatz: Das eine war ausgeschrieben,
     * das andere kam hinzu.
     */
    public function test_lifecycle_separates_addenda_from_the_tender_scope(): void {
        $this->catalog();
        $planned = $this->item('01.0010', '100.000', '10.00');
        $this->assign($planned, '310');

        $addendum = $this->item('N01', '20.000', '10.00');
        $addendum->forceFill(['is_addendum' => true])->save();
        $this->assign($addendum, '310');

        $lifecycle = app(CostGroupReportService::class)->lifecycle($this->bill, null, 2);
        $row = $lifecycle['rows'][0];

        $this->assertSame('310', $row['code']);
        $this->assertSame(1000.0, $row['boq']);
        $this->assertSame(200.0, $row['addenda']);
        // Ohne Aufmaß steht alles noch aus.
        $this->assertSame(0.0, $row['executed']);
        $this->assertSame(1200.0, $row['remaining']);
    }

    /** Aufgemessene Leistung zieht den Rest herunter. */
    public function test_lifecycle_counts_recorded_progress(): void {
        $this->catalog();
        $item = $this->item('01.0010', '100.000', '10.00');
        $this->assign($item, '310');
        app(\App\Services\Gaeb\BoqProgressService::class)->record($item, '60.000');

        $lifecycle = app(CostGroupReportService::class)->lifecycle($this->bill->fresh() ?? $this->bill, null, 2);
        $row = $lifecycle['rows'][0];

        $this->assertSame(600.0, $row['executed']);
        $this->assertSame(400.0, $row['remaining']);
    }

    /**
     * Ein Aufmaß über der LV-Menge ergibt einen negativen Rest — Abweichungen
     * werden ausgewiesen, nicht geglättet.
     */
    public function test_lifecycle_shows_overrun_as_negative_remainder(): void {
        $this->catalog();
        $item = $this->item('01.0010', '100.000', '10.00');
        $this->assign($item, '310');
        app(\App\Services\Gaeb\BoqProgressService::class)->record($item, '130.000');

        $lifecycle = app(CostGroupReportService::class)->lifecycle($this->bill->fresh() ?? $this->bill, null, 2);

        $this->assertSame(-300.0, $lifecycle['rows'][0]['remaining']);
        $this->assertSame(-300.0, $lifecycle['totals']['remaining']);
    }

    /** Auch in der Verfolgung ist „ohne Zuordnung" eine eigene Zeile. */
    public function test_lifecycle_keeps_an_unassigned_row(): void {
        $this->catalog();
        $this->item('01.0010', '10.000', '10.00');

        $lifecycle = app(CostGroupReportService::class)->lifecycle($this->bill, null, 2);

        $this->assertSame('', $lifecycle['rows'][0]['code']);
        $this->assertSame(100.0, $lifecycle['rows'][0]['boq']);
    }

    // ── Zuordnungs-Oberfläche (MVP-639) ──────────────────────────────────

    /** Zuordnen und wieder entfernen — beides über dieselbe Aktion. */
    public function test_item_assignment_can_be_set_and_cleared(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $item = $this->item('01.0010', '10.000', '10.00');

        $this->actingAs($this->admin)
            ->post(route('bill-of-quantities.items.catalog-assignment', $item), ['code' => '310'])
            ->assertRedirect();
        $this->assertSame('310', $item->catalogAssignments()->first()?->code);
        // Von Hand gesetzt bleibt unterscheidbar von importiert.
        $this->assertSame('manual', $item->catalogAssignments()->first()?->source);

        $this->actingAs($this->admin)
            ->post(route('bill-of-quantities.items.catalog-assignment', $item), ['code' => ''])
            ->assertRedirect();
        $this->assertSame(0, $item->catalogAssignments()->count());
    }

    /** Ein Code, den der Stamm nicht kennt, wird abgewiesen. */
    public function test_unknown_code_is_rejected(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $item = $this->item('01.0010', '10.000', '10.00');

        $this->actingAs($this->admin)
            ->post(route('bill-of-quantities.items.catalog-assignment', $item), ['code' => '999'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, $item->catalogAssignments()->count());
    }

    public function test_bulk_assignment_covers_the_selection(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $first = $this->item('01.0010', '10.000', '10.00');
        $second = $this->item('01.0020', '10.000', '10.00');
        $untouched = $this->item('01.0030', '10.000', '10.00');

        $this->actingAs($this->admin)->post(route('bill-of-quantities.catalog-assignment.bulk', $this->bill), [
            'code' => '320',
            'items' => [$first->sqid, $second->sqid],
        ])->assertRedirect();

        $this->assertSame('320', $first->catalogAssignments()->first()?->code);
        $this->assertSame('320', $second->catalogAssignments()->first()?->code);
        $this->assertSame(0, $untouched->catalogAssignments()->count());
    }

    /**
     * Drill-down aus der Auswertung (MVP-642): Der Sprung von der Summe zu den
     * Positionen filtert **auf Präfix** — wer „310" liest, meint auch 311.
     */
    public function test_drilldown_filters_by_cost_group_prefix(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();

        $inGroup = $this->item('01.0010', '10.000', '10.00');
        $this->assign($inGroup, '311');
        $elsewhere = $this->item('01.0020', '10.000', '10.00');
        $this->assign($elsewhere, '320');

        $response = $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.catalog-assignment', [$this->bill, 'code' => '310']));

        $response->assertOk();
        $items = $response->viewData('items');
        $this->assertSame(1, $items->total());
        $this->assertSame($inGroup->id, $items->first()?->id);
        unset($elsewhere);
    }

    /**
     * Teilmengen bekommen eine eigene Zuordnung (MVP-639) — sie schlagen in
     * der Auswertung die der Position.
     */
    public function test_quantity_split_can_be_assigned_separately(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $item = $this->item('01.0010', '100.000', '10.00');
        $split = BoqItemQuantitySplit::query()->create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'quantity' => '60.0000',
            'position' => 1,
        ]);

        $this->actingAs($this->admin)
            ->post(route('bill-of-quantities.splits.catalog-assignment', $split), ['code' => '320'])
            ->assertRedirect();

        $this->assertSame('320', $split->catalogAssignments()->first()?->code);

        // Und die Auswertung rechnet damit: 60 von 100 m³ auf KG 320.
        $report = app(CostGroupReportService::class)->forBill($this->bill, null, 2);
        $rows = collect($report['rows'])->keyBy('code');
        $this->assertSame(600.0, $rows['320']['amount']);
        $this->assertSame(400.0, $report['unassigned']);
    }

    /** Auf der Zuordnungsseite steht die Teilmenge als eigene Zeile. */
    public function test_splits_appear_on_the_assignment_page(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $item = $this->item('01.0010', '100.000', '10.00');
        BoqItemQuantitySplit::query()->create([
            'organization_id' => $this->organization->id,
            'boq_item_id' => $item->id,
            'quantity' => '60.0000',
            'position' => 1,
        ]);

        $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.catalog-assignment', $this->bill))
            ->assertOk()
            ->assertSee('Teilmenge');
    }

    /** Der Arbeitsmodus ist der Filter „ohne Kostengruppe". */
    public function test_filter_shows_only_unassigned_items(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $assigned = $this->item('01.0010', '10.000', '10.00');
        $this->assign($assigned, '310');
        $this->item('01.0020', '10.000', '10.00');

        $response = $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.catalog-assignment', [$this->bill, 'unassigned' => 1]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('items')->total());
    }

    /** Beide Blöcke in einer Datei — getrennte zwängen zum Zusammenfügen. */
    public function test_csv_export_carries_summary_and_lifecycle(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $item = $this->item('01.0010', '10.000', '10.00');
        $this->assign($item, '310');

        $response = $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.cost-groups', [$this->bill, 'export' => 'csv']));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Summen', $body);
        $this->assertStringContainsString('Kostenverfolgung', $body);
        $this->assertStringContainsString('Baugrube, Erdbau', $body);
    }

    /**
     * Der Demo-Ablauf zeigt den ganzen Weg (MVP-649): Das Demo-LV bringt
     * Kostengruppen aus der Datei mit, die Beispielregel füllt den Rest, und
     * die Auswertung rechnet daraus die Summen.
     */
    public function test_demo_seeder_produces_a_cost_group_report(): void {
        (new CatalogRegistrySeeder)->run();
        (new \Database\Seeders\GaebDemoSeeder)->run($this->organization);

        $bill = BillOfQuantity::query()->where('name', 'Demo-LV Neubau Lagerhalle')->firstOrFail();
        $report = app(CostGroupReportService::class)->forBill($bill, null, 2);

        $this->assertNotNull($report['catalog'], 'Das Demo-LV bringt keinen Kostengruppenkatalog mit.');
        $this->assertNotEmpty($report['rows'], 'Die Auswertung des Demo-LV ist leer.');
        // Der Katalogstamm liefert die Kurzbezeichnung zur Nummer.
        $this->assertSame('Baugrube, Erdbau', $report['rows'][0]['label']);
    }

    /**
     * Lasttest der Auswertung (Feature 109, Teststrategie Punkt 5).
     *
     * Ein Bau-LV hat leicht mehrere tausend Positionen. Die Auswertung darf
     * daran nicht in die Knie gehen — und vor allem darf sie **nicht je
     * Position nachladen**: Ein N+1 fällt bei zehn Positionen nicht auf und
     * legt bei fünftausend die Seite lahm.
     *
     * Geprüft wird mit synthetischen Daten statt mit der 30-MB-Referenzdatei:
     * Die liegt außerhalb des Repositorys, und gemessen werden soll ohnehin
     * die Auswertung, nicht der Import.
     */
    public function test_report_scales_to_a_large_bill(): void {
        $this->catalog();

        // Fünftausend Positionen auf zehn Kostengruppen — in einem Rutsch
        // eingefügt, sonst misst der Test das Schreiben statt das Rechnen.
        $now = now();
        $items = [];
        for ($i = 1; $i <= 5000; $i++) {
            $items[] = [
                'organization_id' => $this->organization->id,
                'bill_of_quantity_id' => $this->bill->id,
                'reference_no' => sprintf('01.%04d', $i),
                'short_text' => 'Position ' . $i,
                'quantity' => '10.000',
                'unit' => 'm3',
                'unit_price' => '10.0000',
                'status' => BoqItemStatus::Imported->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($items, 500) as $chunk) {
            BoqItem::query()->insert($chunk);
        }

        $assignments = [];
        foreach (BoqItem::query()->where('bill_of_quantity_id', $this->bill->id)->pluck('id') as $index => $id) {
            $assignments[] = [
                'organization_id' => $this->organization->id,
                'bill_of_quantity_id' => $this->bill->id,
                'assignable_type' => (new BoqItem)->getMorphClass(),
                'assignable_id' => $id,
                'catalog_key' => 'idDIN276-18',
                'code' => (string) (310 + ($index % 8) * 10),
                'source' => 'import',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($assignments, 500) as $chunk) {
            BoqCatalogAssignment::query()->insert($chunk);
        }

        $queries = 0;
        \Illuminate\Support\Facades\DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $start = hrtime(true);
        $report = app(CostGroupReportService::class)->forBill($this->bill, null, 2);
        $seconds = (hrtime(true) - $start) / 1e9;

        $this->assertSame(500000.0, $report['total']);
        $this->assertSame(0.0, $report['unassigned']);
        // Die Zahl der Abfragen darf nicht mit den Positionen wachsen.
        $this->assertLessThan(20, $queries, "Auswertung stellte {$queries} Abfragen — riecht nach N+1.");
        $this->assertLessThan(5.0, $seconds, sprintf('Auswertung dauerte %.1f s.', $seconds));
    }

    public function test_page_renders_with_unassigned_row(): void {
        (new CatalogRegistrySeeder)->run();
        $this->catalog();
        $item = $this->item('01.0010', '10.000', '10.00');
        $this->assign($item, '310');
        $this->item('01.0020', '5.000', '10.00');

        $this->actingAs($this->admin)
            ->get(route('bill-of-quantities.cost-groups', $this->bill))
            ->assertOk()
            ->assertSee('Baugrube, Erdbau')
            ->assertSee('Ohne Zuordnung');
    }
}
