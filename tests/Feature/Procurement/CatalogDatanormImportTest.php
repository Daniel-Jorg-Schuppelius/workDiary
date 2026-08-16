<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogDatanormImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use App\Services\Procurement\DatanormImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050/107 (DATANORM-Import über das erechnung-toolkit): korrekte
 * V4-Preiseinheiten-Codes, echtes V5-Feldlayout, B-Satz-Merge (EAN/
 * Verpackungsmenge), Staffeln, Idempotenz und Upload-Route. Fixtures sind
 * selbst geschrieben (Satzaufbau nach Referenzdoku, keine Lizenzdaten).
 */
final class CatalogDatanormImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private SupplierCatalogSource $source;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'DATANORM', 'format' => 'datanorm', 'delimiter' => ';',
            'decimal_separator' => ',', 'encoding' => 'UTF-8', 'has_header' => true,
        ]);
    }

    /** DATANORM-4-Datei: Festbreiten-Vorlaufsatz, A/B-Sätze, codierte Preiseinheit. */
    private function datanormV4(): string {
        return implode("\r\n", [
            'V ' . '160826' . str_pad('Eigenkatalog Test', 40) . str_pad('', 40) . str_pad('', 35) . '04EUR',
            // Preiseinheit-CODE 2 = je 100 Einheiten (nicht Divisor 2!).
            'A;N;100123;00;Kupferrohr 15x1;hartgeloetet;2;2;m;18950;;CAB;;',
            'B;N;100123;CU15;;;0;0;0;4012345678901; ;ROHRE;90;5; ; ;',
            // Preiseinheit-CODE 0 = je 1 Einheit.
            'A;N;100124;00;T-Stueck 15mm;;2;0;Stk;125;;CAB;;',
            'B;N;100124;TST;;;0;0;0; ; ;FITT;90;1; ; ;',
        ]) . "\r\n";
    }

    /** DATANORM-5-Datei: Semikolon-Vorlaufsatz, 29-Feld-A-Satz, Staffel-Z-Satz. */
    private function datanormV5(): string {
        return implode("\r\n", [
            'V;050;A;20260816;EUR;Eigenkatalog Test;;TESTCO;;;;;;;;',
            'A;N;200001;Flachheizkoerper 500x600;weiss;PCE;2;1;19995;;HK;Flat;;;;;;;4012345678902;;2;;0;;;;;;;',
            'Z;N;200001;01;1;;Staffel ab 10;1;+;2;1;17995;;1;10;99999;',
            'E;4;;',
        ]) . "\r\n";
    }

    public function test_v4_import_decodes_price_unit_codes_and_merges_b_records(): void {
        $summary = app(DatanormImportService::class)->import($this->source, $this->datanormV4());

        $this->assertSame(2, $summary['created']);
        $this->assertSame(2, $summary['rows']);

        $rohr = SupplierCatalogItem::query()->where('external_no', '100123')->firstOrFail();
        // 18950 Cent je 100 (Code 2) → 1,8950 je Meter — früherer Bug: ÷2 = 94,75.
        $this->assertSame('1.8950', $rohr->purchase_price?->getAmount());
        $this->assertStringContainsString('Kupferrohr', (string) $rohr->name);
        $this->assertSame('CAB', $rohr->category);
        // Aus dem B-Satz übernommen:
        $this->assertSame('4012345678901', $rohr->gtin);
        $this->assertSame('5.0000', (string) $rohr->pack_size);

        // 125 Cent je 1 (Code 0) → 1,25.
        $this->assertSame('1.2500', SupplierCatalogItem::query()->where('external_no', '100124')->firstOrFail()->purchase_price?->getAmount());
    }

    public function test_v5_import_uses_the_v5_field_layout(): void {
        $summary = app(DatanormImportService::class)->import($this->source, $this->datanormV5());

        $this->assertSame(1, $summary['created']);

        $heizkoerper = SupplierCatalogItem::query()->where('external_no', '200001')->firstOrFail();
        // Preis steht in V5 auf Feld 9 — früherer Bug las die Rabattgruppe (→ 0,03 €).
        $this->assertSame('199.9500', $heizkoerper->purchase_price?->getAmount());
        $this->assertSame('Flachheizkoerper 500x600 weiss', $heizkoerper->name);
        $this->assertSame('HK', $heizkoerper->category);
        $this->assertSame('4012345678902', $heizkoerper->gtin);
        $this->assertSame('2.0000', (string) $heizkoerper->pack_size);

        // Staffelpreis-Z-Satz → Mengenstaffel (179,95 ab 10 Stück).
        $tiers = $heizkoerper->priceTiers()->get();
        $this->assertCount(1, $tiers);
        $this->assertSame('10.0000', (string) $tiers[0]->min_qty);
        $this->assertSame('179.9500', $tiers[0]->unit_price?->getAmount());
    }

    public function test_reimport_is_idempotent(): void {
        app(DatanormImportService::class)->import($this->source, $this->datanormV4());
        $summary = app(DatanormImportService::class)->import($this->source, $this->datanormV4());

        $this->assertSame(0, $summary['created']);
        $this->assertSame(2, $summary['unchanged']);
    }

    public function test_rab_file_turns_list_prices_into_net_purchase_prices(): void {
        // Artikel mit Listenpreis (Kennzeichen 1) + Rabattgruppe, RAB noch unbekannt.
        app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;A;20260816;EUR;Katalog;;TESTCO;;;;;;;;',
            'A;N;300001;Heizkoerper Basis;;PCE;1;1;10000;A001;HK;;;;;;;;;;1;;0;;;;;;;',
            'E;3;;',
        ]) . "\r\n");

        $item = SupplierCatalogItem::query()->where('external_no', '300001')->firstOrFail();
        // Ohne bekannte Rabattgruppe bleibt der Listenpreis als EK-Obergrenze stehen.
        $this->assertSame('100.0000', $item->purchase_price?->getAmount());
        $this->assertSame('100.0000', $item->list_price?->getAmount());
        $this->assertSame('list', $item->price_type);
        $this->assertSame('A001', $item->discount_group);

        // RAB-Lieferung: 20 % Rabatt → EK wird neu berechnet, inkl. Preishistorie.
        $summary = app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;R;20260816;EUR;Rabatte;;TESTCO;;;;;;;;',
            'R;A001;1;2000;Heizkoerper (20%);',
            'E;3;;',
        ]) . "\r\n");

        $this->assertSame(1, $summary['price_changed']);
        $item->refresh();
        $this->assertSame('80.0000', $item->purchase_price?->getAmount());
        $this->assertSame('100.0000', $item->list_price?->getAmount());
        $this->assertSame(1, \App\Models\SupplierCatalogDiscountGroup::query()->where('supplier_catalog_source_id', $this->source->id)->count());

        // Folgekatalog mit bekannter Gruppe rechnet direkt beim Import.
        app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;A;20260816;EUR;Katalog;;TESTCO;;;;;;;;',
            'A;N;300002;Heizkoerper Komfort;;PCE;1;1;20000;A001;HK;;;;;;;;;;1;;0;;;;;;;',
            'E;3;;',
        ]) . "\r\n");

        $this->assertSame('160.0000', SupplierCatalogItem::query()->where('external_no', '300002')->firstOrFail()->purchase_price?->getAmount());
    }

    public function test_wrg_file_stores_product_group_labels(): void {
        app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;S;20260816;EUR;Warengruppen;;TESTCO;;;;;;;;',
            'S;HK;;Heizkoerper;',
            'S;HK;Flat;Flachheizkoerper;',
            'E;4;;',
        ]) . "\r\n");

        $groups = \App\Models\SupplierCatalogProductGroup::query()->where('supplier_catalog_source_id', $this->source->id)->get();
        $this->assertCount(2, $groups);
        $this->assertSame('Heizkoerper', $groups->firstWhere('group', '')?->label);
        $this->assertSame('Flachheizkoerper', $groups->firstWhere('group', 'Flat')?->label);
    }

    public function test_datpreis_updates_prices_as_delta_using_stored_price_unit(): void {
        app(DatanormImportService::class)->import($this->source, $this->datanormV4());

        // V4-DATPREIS trägt keine Preiseinheit — es gilt die gespeicherte je 100.
        $summary = app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V ' . '170826' . str_pad('Preisdatei', 40) . str_pad('', 40) . str_pad('', 35) . '04EUR',
            'P;A;100123;2;20950;;;;;;;;;;;;;;;;;;;;;;;;;',
        ]) . "\r\n");

        $this->assertSame(1, $summary['price_changed']);
        $this->assertSame(0, $summary['discontinued']);

        // 20950 Cent je 100 → 2,0950 je Meter.
        $this->assertSame('2.0950', SupplierCatalogItem::query()->where('external_no', '100123')->firstOrFail()->purchase_price?->getAmount());
        // Nicht in der Preisdatei enthaltene Artikel bleiben unangetastet (Delta).
        $this->assertSame('1.2500', SupplierCatalogItem::query()->where('external_no', '100124')->firstOrFail()->purchase_price?->getAmount());
    }

    public function test_change_file_renames_deletes_and_keeps_untouched_fields(): void {
        app(DatanormImportService::class)->import($this->source, $this->datanormV5());
        app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;A;20260816;EUR;Katalog2;;TESTCO;;;;;;;;',
            'A;N;200002;Zweiter Artikel;;PCE;2;1;5000;;HK;;;;;;;;;;1;;0;;;;;;;',
            'E;3;;',
        ]) . "\r\n");

        // Änderungsdatei: 200001 umnummerieren, 200002 löschen, Preis von 200001-N ändern.
        $summary = app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;A;20260817;EUR;Aenderung;;TESTCO;;;;;;;;',
            'B;A;200001;200001-N;',
            'B;L;200002;200001-N;20261231;',
            'A;A;200001-N;;;;2;1;18995;;;;',
            'E;5;;',
        ]) . "\r\n");

        $this->assertSame(0, $summary['discontinued']); // Delta kündigt nicht ab.

        $renamed = SupplierCatalogItem::query()->where('external_no', '200001-N')->firstOrFail();
        // Preis aktualisiert, Name/GTIN aus dem Bestand unangetastet (Leerfelder!).
        $this->assertSame('189.9500', $renamed->purchase_price?->getAmount());
        $this->assertSame('Flachheizkoerper 500x600 weiss', $renamed->name);
        $this->assertSame('4012345678902', $renamed->gtin);

        $deleted = SupplierCatalogItem::query()->where('external_no', '200002')->firstOrFail();
        $this->assertSame(\App\Enums\Procurement\CatalogItemStatus::Discontinued, $deleted->status);
        $this->assertSame('200001-N', ($deleted->extra_attributes ?? [])['datanorm_successor'] ?? null);

        // Verpackungsmenge war im Änderungssatz nicht übertragen → unangetastet.
        $this->assertSame('2.0000', (string) $renamed->pack_size);
    }

    public function test_auto_snapshot_guard_blocks_suspicious_full_imports(): void {
        // 12 Bestandsartikel anlegen.
        $lines = ['V;050;A;20260816;EUR;Katalog;;TESTCO;;;;;;;;'];
        for ($i = 1; $i <= 12; $i++) {
            $lines[] = "A;N;G-{$i};Artikel {$i};;PCE;2;1;1000;;;;";
        }
        $lines[] = 'E;' . (count($lines) + 1) . ';;';
        app(DatanormImportService::class)->import($this->source, implode("\r\n", $lines) . "\r\n");

        // „Vollkatalog" mit nur einem Artikel würde 12/12 abkündigen → Schranke.
        $tiny = implode("\r\n", [
            'V;050;A;20260817;EUR;Katalog;;TESTCO;;;;;;;;',
            'A;N;G-NEU;Einzelner Artikel;;PCE;2;1;1000;;;;',
            'E;3;;',
        ]) . "\r\n";

        try {
            app(DatanormImportService::class)->import($this->source, $tiny);
            $this->fail('Schutzschranke hätte greifen müssen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('12', $e->getMessage());
        }
        $this->assertSame(0, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $this->source->id)
            ->where('status', \App\Enums\Procurement\CatalogItemStatus::Discontinued)->count());

        // Explizit als Vollkatalog erklärt → Abkündigung ist gewollt.
        $summary = app(DatanormImportService::class)->import($this->source, $tiny, DatanormImportService::MODE_SNAPSHOT);
        $this->assertSame(12, $summary['discontinued']);
    }

    public function test_delta_reimport_does_not_flap_counters(): void {
        app(DatanormImportService::class)->import($this->source, $this->datanormV4());

        $datpreis = implode("\r\n", [
            'V ' . '170826' . str_pad('Preisdatei', 40) . str_pad('', 40) . str_pad('', 35) . '04EUR',
            'P;A;100123;2;20950;;;;;;;;;;;;;;;;;;;;;;;;;',
        ]) . "\r\n";

        $first = app(DatanormImportService::class)->import($this->source, $datpreis);
        $this->assertSame(1, $first['price_changed']);

        // Identische Preisdatei erneut: keine Änderung, kein „updated"-Rauschen.
        $second = app(DatanormImportService::class)->import($this->source, $datpreis);
        $this->assertSame(0, $second['price_changed']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(1, $second['unchanged']);

        // Und der nächste Vollimport bleibt idempotent (raw_hash unangetastet…
        // bis auf den Artikel, dessen Preis sich real geändert hat).
        $third = app(DatanormImportService::class)->import($this->source, $this->datanormV4());
        $this->assertSame(1, $third['price_changed']); // 100123 zurück auf 1,8950
        $this->assertSame(1, $third['unchanged']);     // 100124 unverändert
    }

    public function test_future_dated_prices_are_parked_and_applied_by_command(): void {
        app(DatanormImportService::class)->import($this->source, $this->datanormV4());

        $validFrom = now()->addDay()->format('Ymd');
        $summary = app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;P;20260816;EUR;Preisdatei;;TESTCO;;;;;;;;',
            "P;100123;2;100;20950;;;;;;;;{$validFrom};",
            'E;3;;',
        ]) . "\r\n");

        // Noch nicht wirksam — nur vorgemerkt.
        $this->assertSame(0, $summary['price_changed']);
        $item = SupplierCatalogItem::query()->where('external_no', '100123')->firstOrFail();
        $this->assertSame('1.8950', $item->purchase_price?->getAmount());
        $pending = ($item->extra_attributes ?? [])[DatanormImportService::PENDING_PRICE_KEY] ?? null;
        $this->assertIsArray($pending);
        $this->assertSame('2.0950', $pending['purchase_price'] ?? null);

        // Vor dem Stichtag tut der Lauf nichts.
        $this->artisan('catalog:apply-pending-prices')->assertExitCode(0);
        $this->assertSame('1.8950', $item->fresh()?->purchase_price?->getAmount());

        // Am Stichtag wird der Preis über den Delta-Upsert wirksam.
        $this->travelTo(now()->addDays(2));
        $this->artisan('catalog:apply-pending-prices')->assertExitCode(0);
        $item->refresh();
        $this->assertSame('2.0950', $item->purchase_price?->getAmount());
        $this->assertArrayNotHasKey(DatanormImportService::PENDING_PRICE_KEY, $item->extra_attributes ?? []);
        $this->travelBack();
    }

    public function test_unresolved_list_price_suppresses_margin_alert(): void {
        // Artikel mit VK 10 € — ein „EK" von 100 € (Listen-Obergrenze) wäre
        // sonst eine sichere Margen-Warnung.
        $article = \App\Models\Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'INT-1', 'name' => 'Verknüpfter Artikel',
            'status' => 'active', 'sellable' => true, 'default_sale_price' => '10',
        ]);

        app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;A;20260816;EUR;Katalog;;TESTCO;;;;;;;;',
            'A;N;400001;Listenpreis-Artikel;;PCE;1;1;5000;U999;HK;;;;;;;;;;1;;0;;;;;;;',
            'E;3;;',
        ]) . "\r\n");
        SupplierCatalogItem::query()->where('external_no', '400001')
            ->update(['article_id' => $article->id]);

        // Preisänderung mit weiterhin unauflösbarer Rabattgruppe → keine Warnung.
        app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;P;20260816;EUR;Preise;;TESTCO;;;;;;;;',
            'P;400001;1;1;10000;U999;;;;;;;;',
            'E;3;;',
        ]) . "\r\n");
        $this->assertSame(0, \App\Models\PricingChangeAlert::query()->count());

        // Sobald die Rabattgruppe bekannt ist, wird regulär bewertet.
        app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;R;20260816;EUR;Rabatte;;TESTCO;;;;;;;;',
            'R;U999;1;1000;Testgruppe (10%);',
            'E;3;;',
        ]) . "\r\n");
        $this->assertGreaterThan(0, \App\Models\PricingChangeAlert::query()->count());
    }

    public function test_customer_mismatch_rejects_the_file(): void {
        $this->source->forceFill(['expected_customer_no' => 'KD-1'])->save();

        $this->expectException(\RuntimeException::class);
        app(DatanormImportService::class)->import($this->source, implode("\r\n", [
            'V;050;P;20260816;EUR;Preise;;TESTCO;;;;;;;;',
            'K;KD-9;Fremder Kunde;;;Weg 1;D;11111;Stadt;',
            'P;100123;2;1;1000;;;;;;;;;',
            'E;4;;',
        ]) . "\r\n");
    }

    public function test_upload_route_imports_datanorm_file(): void {
        $file = UploadedFile::fake()->createWithContent('DATANORM.001', $this->datanormV4());

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.import', $this->source), ['catalog_csv' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $this->source->id)->count());
    }

    public function test_import_mode_delta_overrides_snapshot_discontinuation(): void {
        app(DatanormImportService::class)->import($this->source, $this->datanormV5());

        // Vollkatalog-Datei (nur N-Sätze), aber explizit als Änderungsdatei importiert:
        // der nicht enthaltene Artikel 200001 darf NICHT abgekündigt werden.
        $file = UploadedFile::fake()->createWithContent('DATANORM.002', $this->datanormV4());
        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.import', $this->source), ['catalog_csv' => $file, 'import_mode' => 'delta'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            \App\Enums\Procurement\CatalogItemStatus::New,
            SupplierCatalogItem::query()->where('external_no', '200001')->firstOrFail()->status
        );
    }
}
