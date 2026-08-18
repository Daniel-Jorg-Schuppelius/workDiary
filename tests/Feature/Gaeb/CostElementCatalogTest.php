<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostElementCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Models\Costing\{CostElement, CostElementCatalog};
use App\Models\User;
use App\Services\Gaeb\CostElementCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Baukostenkatalog X50 (Feature 109, MVP-645).
 *
 * Ein Katalog ist ein **Nachschlagewerk**, kein Vorhaben: Er sagt, was ein
 * Bauteil üblicherweise kostet. Der **Kennwert ist eine Spanne** — von, Mittel
 * und bis stehen nebeneinander, weil nur der Mittelwert verschwiege, wie
 * sicher er ist.
 */
final class CostElementCatalogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** X50.2 — vollständige Nummern (`EleNo`), mit Kennwertspanne und Hierarchie. */
    private function x50(): string {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <GAEB xmlns="http://www.gaeb.de/GAEB_DA_XML/DA50/3.3">
          <GAEBInfo><Version>3.3</Version><Date>2026-08-18</Date><ProgSystem>Test</ProgSystem></GAEBInfo>
          <PrjInfo><NamePrj>Kennwerte 2026</NamePrj><Cur>EUR</Cur></PrjInfo>
          <ElementalCosting>
            <DP>50</DP>
            <ECInfo><Name>BKI 2026</Name><LblEC>Kennwerte Hochbau 2026</LblEC><Date>2026-01-01</Date><Cur>EUR</Cur></ECInfo>
            <ECBody>
              <CostElement>
                <EleNo>300</EleNo>
                <Descr>Bauwerk - Baukonstruktionen</Descr>
                <QU>m2</QU>
                <UPFrom>1200.0000</UPFrom>
                <UPAvg>1450.0000</UPAvg>
                <UPTo>1800.0000</UPTo>
                <CostElement>
                  <EleNo>330</EleNo>
                  <Descr>Aussenwaende</Descr>
                  <QU>m2</QU>
                  <UPFrom>280.0000</UPFrom>
                  <UPAvg>320.0000</UPAvg>
                  <UPTo>410.0000</UPTo>
                </CostElement>
              </CostElement>
            </ECBody>
          </ElementalCosting>
        </GAEB>
        XML;
    }

    public function test_import_keeps_the_benchmark_range_and_hierarchy(): void {
        $catalog = app(CostElementCatalogService::class)
            ->import($this->x50(), $this->organization->id, $this->admin);

        $this->assertSame('Kennwerte Hochbau 2026', $catalog->name);
        $this->assertTrue($catalog->full_element_numbers);
        $this->assertSame('2026-01-01', $catalog->valid_on?->toDateString());

        $elements = $catalog->elements()->get();
        $this->assertCount(2, $elements);

        $root = $elements->firstWhere('code', '300');
        $this->assertSame('1200.0000', $root?->unit_price_from);
        $this->assertSame('1450.0000', $root?->unit_price_avg);
        $this->assertSame('1800.0000', $root?->unit_price_to);

        // Die Hierarchie hängt am Elternschlüssel.
        $child = $elements->firstWhere('code', '330');
        $this->assertSame('300', $child?->parent_code);
        $this->assertSame(2, $child?->level);
    }

    /** Der Rechenwert ist der Mittelwert; ohne ihn die Mitte der Spanne. */
    public function test_benchmark_falls_back_to_the_middle_of_the_range(): void {
        $catalog = CostElementCatalog::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Handkatalog',
            'source' => CostElementCatalog::SOURCE_MANUAL,
            'created_by' => $this->admin->id,
        ]);

        $withAvg = CostElement::query()->create([
            'cost_element_catalog_id' => $catalog->id,
            'label' => 'Mit Mittelwert', 'unit_price_from' => '100', 'unit_price_avg' => '150', 'unit_price_to' => '300',
        ]);
        $spanOnly = CostElement::query()->create([
            'cost_element_catalog_id' => $catalog->id,
            'label' => 'Nur Spanne', 'unit_price_from' => '100', 'unit_price_to' => '300',
        ]);
        $nothing = CostElement::query()->create([
            'cost_element_catalog_id' => $catalog->id,
            'label' => 'Ohne Kennwert',
        ]);

        $this->assertSame(150.0, $withAvg->benchmark());
        $this->assertSame(200.0, $spanOnly->benchmark());
        // Eine erfundene Null wäre schlimmer als ein Element ohne Kennwert.
        $this->assertNull($nothing->benchmark());
    }

    /** Der Export gibt zurück, was hereinkam — samt Spanne und Bauform. */
    public function test_export_survives_a_round_trip(): void {
        $catalog = app(CostElementCatalogService::class)
            ->import($this->x50(), $this->organization->id, $this->admin);

        $xml = app(CostElementCatalogService::class)->export($catalog);
        $this->assertStringContainsString('<EleNo>300</EleNo>', $xml);
        // X50.1 nummeriert in Teilen — diese Datei tut es nicht.
        $this->assertStringNotContainsString('<ElePart>', $xml);

        $again = (new \ERechnungToolkit\Parsers\GaebCostingParser)->parse($xml);
        $elements = $again->getElements();

        $this->assertCount(1, $elements);
        $this->assertSame('300', $elements[0]->getNumber());
        $this->assertSame(1450.0, $elements[0]->getUnitPriceAverage()?->toFloat());
        $this->assertSame(1200.0, $elements[0]->getUnitPriceFrom()?->toFloat());
        // Das Kind reist mit.
        $this->assertCount(1, $elements[0]->getChildren());
        $this->assertSame('330', $elements[0]->getChildren()[0]->getNumber());
    }

    public function test_upload_and_listing_work(): void {
        $this->actingAs($this->admin)->post(route('cost-catalogs.store'), [
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('kennwerte.x50', $this->x50()),
        ])->assertRedirect();

        $catalog = CostElementCatalog::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('cost-catalogs.index'))
            ->assertOk()
            ->assertSee('Kennwerte Hochbau 2026');

        $this->actingAs($this->admin)
            ->get(route('cost-catalogs.show', $catalog))
            ->assertOk()
            ->assertSee('Aussenwaende');
    }

    /**
     * Kennwert und Artikelstamm (MVP-645): Die Verknüpfung stellt beides
     * nebeneinander — **übernommen wird nichts**.
     */
    public function test_benchmark_can_be_linked_to_an_article(): void {
        $catalog = app(CostElementCatalogService::class)
            ->import($this->x50(), $this->organization->id, $this->admin);
        $element = $catalog->elements()->where('code', '330')->firstOrFail();

        $article = \App\Models\Article::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Außenwand zweischalig',
        ]);

        $this->actingAs($this->admin)->post(
            route('cost-catalogs.link-article', [$catalog, $element]),
            ['article' => $article->sqid],
        )->assertRedirect();

        $this->assertSame($article->id, $element->refresh()->article_id);
        // Der Artikelpreis bleibt unangetastet — der Kennwert ist ein
        // Anhaltspunkt, keine Kalkulation.
        $this->assertSame(1, $article->costBenchmarks()->count());

        // Und die Artikelseite zeigt ihn.
        $this->actingAs($this->admin)
            ->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee('Kennwerte aus Baukostenkatalogen');
    }

    /** Ein leerer Wert löst die Verknüpfung wieder. */
    public function test_link_can_be_cleared(): void {
        $catalog = app(CostElementCatalogService::class)
            ->import($this->x50(), $this->organization->id, $this->admin);
        $element = $catalog->elements()->where('code', '330')->firstOrFail();
        $article = \App\Models\Article::factory()->create(['organization_id' => $this->organization->id]);
        $element->update(['article_id' => $article->id]);

        $this->actingAs($this->admin)
            ->post(route('cost-catalogs.link-article', [$catalog, $element]), ['article' => ''])
            ->assertRedirect();

        $this->assertNull($element->refresh()->article_id);
    }

    /** Fremde Kataloge sind nicht erreichbar. */
    public function test_foreign_catalog_is_not_reachable(): void {
        $other = \App\Models\Organization::factory()->create();
        $foreign = CostElementCatalog::query()->create([
            'organization_id' => $other->id,
            'name' => 'Fremd',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('cost-catalogs.show', $foreign))->assertNotFound();
    }
}
