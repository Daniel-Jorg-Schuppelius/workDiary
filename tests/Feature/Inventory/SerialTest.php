<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{SerialSource, SerialStatus};
use App\Models\{Article, ArticleVariant, Customer, StockDelivery, StockSerial, Warehouse};
use App\Services\Inventory\{SerialNumberGenerator, SerialService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Seriennummern-Lebenslauf (Feature 047/048, E2): Eigenfertigungs-Generierung,
 * Dublettensperre, Versandbindung, Sperre, Provenienz-/Betrugsprüfung,
 * Mandantentrennung sowie Luhn-Prüfziffer.
 */
final class SerialTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private SerialService $serials;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->serials = app(SerialService::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'serial_required' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default',
        ]);
    }

    public function test_generate_creates_unique_instock_serials(): void {
        $serials = $this->serials->generate($this->variant, 3, $this->warehouse);

        $this->assertCount(3, $serials);
        $this->assertSame(3, StockSerial::query()->count());
        $this->assertSame(3, collect($serials)->pluck('serial_no')->unique()->count());
        foreach ($serials as $serial) {
            $this->assertSame(SerialStatus::InStock, $serial->status);
            $this->assertSame(SerialSource::Manufactured, $serial->source);
            $this->assertSame($this->warehouse->id, $serial->warehouse_id);
        }
    }

    public function test_duplicate_serial_is_rejected(): void {
        $this->serials->register($this->variant, 'ABC-1', SerialSource::Purchased, $this->warehouse);

        $this->expectException(RuntimeException::class);
        $this->serials->register($this->variant, 'ABC-1', SerialSource::Purchased, $this->warehouse);
    }

    public function test_ship_binds_customer_and_delivery(): void {
        $serial = $this->serials->register($this->variant, 'SHIP-1', SerialSource::Manufactured, $this->warehouse);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $delivery = $this->makeDelivery($customer);

        $this->serials->ship($serial, $delivery, $customer);

        $fresh = $serial->fresh();
        $this->assertSame(SerialStatus::Shipped, $fresh->status);
        $this->assertSame($customer->id, $fresh->customer_id);
        $this->assertSame($delivery->id, $fresh->stock_delivery_id);
        $this->assertNotNull($fresh->shipped_at);
    }

    public function test_blocked_serial_cannot_ship(): void {
        $serial = $this->serials->register($this->variant, 'BLK-1', SerialSource::Manufactured, $this->warehouse);
        $this->serials->block($serial, 'gestohlen');

        $this->assertSame(SerialStatus::Blocked, $serial->fresh()->status);

        $this->expectException(RuntimeException::class);
        $this->serials->ship($serial->fresh(), $this->makeDelivery(null));
    }

    public function test_was_shipped_to_detects_provenance(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $serial = $this->serials->register($this->variant, 'PROV-1', SerialSource::Manufactured, $this->warehouse);
        $this->serials->ship($serial, $this->makeDelivery($customer), $customer);

        $this->assertTrue($this->serials->wasShippedTo($this->organization->id, 'PROV-1', $customer));
        $this->assertFalse($this->serials->wasShippedTo($this->organization->id, 'PROV-1', $other));
    }

    public function test_lookup_is_isolated_per_organization(): void {
        $this->serials->register($this->variant, 'ISO-1', SerialSource::Manufactured, $this->warehouse);

        $this->assertNotNull($this->serials->lookup($this->organization->id, 'ISO-1'));
        $this->assertNull($this->serials->lookup($this->organization->id + 999, 'ISO-1'));
    }

    public function test_generated_serial_follows_article_scheme(): void {
        $this->variant->article->update(['serial_scheme' => ['prefix' => 'CAM', 'padding' => 4, 'check_digit' => false]]);

        $serials = $this->serials->generate($this->variant->fresh(), 1, $this->warehouse);

        $this->assertSame('CAM-0001', $serials[0]->serial_no);
    }

    public function test_capture_rejects_blocklisted_serial(): void {
        $blocked = $this->serials->register($this->variant, 'BL-1', SerialSource::Manufactured, $this->warehouse);
        $this->serials->block($blocked, 'gestohlen');

        $otherArticle = Article::factory()->create(['organization_id' => $this->organization->id]);
        $other = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $otherArticle->id, 'option_signature' => 'o2',
        ]);

        $this->expectException(RuntimeException::class);
        $this->serials->captureForReceipt($other, 'BL-1', $this->warehouse);
    }

    public function test_luhn_check_digit_matches_known_example(): void {
        // Klassisches Luhn-Beispiel: 7992739871 → Prüfziffer 3.
        $this->assertSame(3, app(SerialNumberGenerator::class)->luhnCheckDigit('7992739871'));
    }

    private function makeDelivery(?Customer $customer): StockDelivery {
        return StockDelivery::query()->create([
            'organization_id' => $this->organization->id,
            'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer?->id,
            'quantity' => '1',
            'unit' => 'Stk',
            'name_snapshot' => 'Gerät',
            'stock_status' => 'delivered',
            'facturation_status' => 'pending',
            'delivered_at' => Carbon::now(),
        ]);
    }
}
