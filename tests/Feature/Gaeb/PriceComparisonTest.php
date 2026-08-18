<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceComparisonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Models\{BillOfQuantity, BoqItem, BoqItemPriceSnapshot, GaebImport};
use App\Services\Gaeb\PriceComparisonService;
use CommonToolkit\Enums\CurrencyCode;
use ERechnungToolkit\Enums\GaebPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Preisspiegel: mehrere Angebote zu einer Ausschreibung nebeneinander.
 * Gekennzeichnet wird, nicht gewertet — die Vergabeordnung verlangt bei
 * ungewöhnlich niedrigen Angeboten Aufklärung, nicht Ausschluss.
 */
final class PriceComparisonTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private BillOfQuantity $boq;

    /** @var list<BoqItem> */
    private array $items = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->boq = BillOfQuantity::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Rohbau',
            'status' => 'draft',
        ]);

        foreach ([['001.0010', 'Aushub'], ['001.0020', 'Beton']] as $position => [$reference, $text]) {
            $this->items[] = BoqItem::query()->create([
                'organization_id' => $this->organization->id,
                'bill_of_quantity_id' => $this->boq->id,
                'reference_no' => $reference,
                'short_text' => $text,
                'quantity' => '10.000',
                'unit' => 'm3',
                'position' => $position + 1,
            ]);
        }
    }

    /** @param array<int, string> $unitPrices Positionsindex => Einheitspreis */
    private function bid(string $filename, array $unitPrices): GaebImport {
        $import = GaebImport::query()->create([
            'organization_id' => $this->organization->id,
            'bill_of_quantity_id' => $this->boq->id,
            'filename' => $filename,
            'file_hash' => hash('sha256', $filename),
            'phase' => GaebPhase::Bid->value,
            'status' => 'imported',
        ]);

        foreach ($unitPrices as $index => $price) {
            BoqItemPriceSnapshot::query()->create([
                'organization_id' => $this->organization->id,
                'boq_item_id' => $this->items[$index]->id,
                'gaeb_import_id' => $import->id,
                'phase' => GaebPhase::Bid->value,
                'unit_price' => $price,
                'total_price' => bcmul($price, '10', 2),
                'currency' => CurrencyCode::Euro->value,
                'captured_at' => now(),
            ]);
        }

        return $import;
    }

    /** Die Angebote stehen nach Summe sortiert nebeneinander. */
    public function test_bidders_are_ranked_by_their_total(): void {
        $cheap = $this->bid('bieter-a.x84', [0 => '10.00', 1 => '20.00']);
        $expensive = $this->bid('bieter-b.x84', [0 => '12.00', 1 => '25.00']);

        $result = app(PriceComparisonService::class)->compare($this->boq);

        $this->assertCount(2, $result['bidders']);
        $this->assertSame($cheap->id, $result['bidders'][0]['import_id']);
        $this->assertSame(1, $result['bidders'][0]['rank']);
        $this->assertSame($expensive->id, $result['bidders'][1]['import_id']);
        $this->assertSame('300,00 €', $result['bidders'][0]['total']->format());
    }

    /**
     * Der Abstand misst sich zum **nächstgünstigeren** Angebot — genau darauf
     * zielt die Aufklärungspflicht bei ungewöhnlich niedrigen Preisen.
     */
    public function test_unusually_low_bid_is_flagged(): void {
        $this->bid('bieter-a.x84', [0 => '10.00', 1 => '20.00']);   // Summe 300
        $this->bid('bieter-b.x84', [0 => '20.00', 1 => '40.00']);   // Summe 600

        $result = app(PriceComparisonService::class)->compare($this->boq);

        $this->assertSame(50.0, $result['bidders'][0]['gap_percent']);
        $this->assertTrue($result['bidders'][0]['unusually_low']);
        // Nur das günstigste Angebot trägt den Abstand; die anderen nicht.
        $this->assertNull($result['bidders'][1]['gap_percent']);
        $this->assertFalse($result['bidders'][1]['unusually_low']);
    }

    /** Je Position: günstigster Bieter und die Spanne zwischen den Preisen. */
    public function test_rows_name_the_cheapest_and_the_spread(): void {
        $cheap = $this->bid('bieter-a.x84', [0 => '10.00', 1 => '20.00']);
        $this->bid('bieter-b.x84', [0 => '15.00', 1 => '20.00']);

        $result = app(PriceComparisonService::class)->compare($this->boq);

        $this->assertSame($cheap->id, $result['rows'][0]['cheapest_import_id']);
        $this->assertSame(50.0, $result['rows'][0]['spread_percent']);
        // Gleiche Preise: keine Spanne.
        $this->assertSame(0.0, $result['rows'][1]['spread_percent']);
    }

    /**
     * Wer eine Position nicht angeboten hat, erscheint dort nicht — ein
     * fehlender Preis ist kein Preis von null.
     */
    public function test_missing_position_is_a_gap_not_a_zero(): void {
        $this->bid('bieter-a.x84', [0 => '10.00', 1 => '20.00']);
        $this->bid('bieter-b.x84', [0 => '12.00']);

        $result = app(PriceComparisonService::class)->compare($this->boq);

        $this->assertFalse($result['complete']);
        $this->assertCount(1, $result['rows'][1]['prices']);
    }

    /** Die Ansicht zeigt den Spiegel und hebt den günstigsten Preis hervor. */
    public function test_page_renders_the_comparison(): void {
        $this->bid('bieter-a.x84', [0 => '10.00', 1 => '20.00']);
        $this->bid('bieter-b.x84', [0 => '15.00', 1 => '20.00']);

        $admin = \App\Models\User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('bill-of-quantities.price-comparison', $this->boq))
            ->assertOk()
            ->assertSee('bieter-a.x84')
            ->assertSee('bieter-b.x84');
    }

    /** Ohne Angebote bleibt der Spiegel leer, statt zu scheitern. */
    public function test_without_bids_the_comparison_is_empty(): void {
        $result = app(PriceComparisonService::class)->compare($this->boq);

        $this->assertSame([], $result['bidders']);
        $this->assertSame([], $result['rows']);
    }
}
