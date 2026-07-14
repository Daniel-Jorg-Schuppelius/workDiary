<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierScorecardServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Inventory\{OwnershipType, StockMovementType, StockState};
use App\Enums\Isms\{IncidentSeverity, SupplierAssessmentStatus};
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{Article, ArticleVariant, PurchaseOrder, PurchaseOrderLine, StockMovement, Supplier, Warehouse};
use App\Models\Claims\ClaimCase;
use App\Models\Isms\IsmsSupplierAssessment;
use App\Services\Procurement\SupplierScorecardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lieferantenperformance-Scorecards (Bauturbo Welle D): rechnet die Kennzahlen
 * aus Hand-Fixtures nach (SQLite-Falle: Aggregate nie „on the fly" glauben,
 * sondern gegen bekannte Werte prüfen). Termintreue, Reklamationsquote,
 * Preistrend, „keine Daten"-Behandlung und die Gesamt-Score-Gewichtung.
 */
final class SupplierScorecardServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Supplier $supplier;
    private Warehouse $warehouse;
    private Article $article;
    private ArticleVariant $variant;
    private SupplierScorecardService $service;
    private CarbonImmutable $from;
    private CarbonImmutable $to;
    private int $orderSeq = 0;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
        $this->service = app(SupplierScorecardService::class);
        $this->from = CarbonImmutable::parse('2026-06-01')->startOfDay();
        $this->to = CarbonImmutable::parse('2026-06-30')->endOfDay();
    }

    // ── Termintreue ──────────────────────────────────────────────────────

    public function test_on_time_rate_counts_punctual_and_late_correctly(): void {
        // Pünktlich: Lieferung am 05.06. gegen zugesagten 10.06.
        $this->makeReceivedOrder('2026-06-10', '2026-06-05', '2026-06-01', '10.00');
        // Verspätet: Lieferung am 20.06. gegen zugesagten 10.06.
        $this->makeReceivedOrder('2026-06-10', '2026-06-20', '2026-06-02', '10.00');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertTrue($card['ontime']['available']);
        $this->assertSame(2, $card['ontime']['evaluated']);
        $this->assertSame(1, $card['ontime']['on_time']);
        $this->assertSame(1, $card['ontime']['late']);
        $this->assertEqualsWithDelta(0.5, $card['ontime']['rate'], 0.0001);
        $this->assertSame(50, $card['ontime']['goodness']);
    }

    public function test_on_time_ignores_orders_without_goods_receipt(): void {
        // Bestellt, zugesagt, aber kein Wareneingang gebucht → keine Aussage.
        $this->makeOrder(PurchaseOrderStatus::Ordered, '2026-06-10', '2026-06-01', '10.00');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertFalse($card['ontime']['available']);
        $this->assertNull($card['ontime']['rate']);
        $this->assertSame(0, $card['ontime']['evaluated']);
    }

    // ── Reklamationsquote ────────────────────────────────────────────────

    public function test_complaint_rate_is_claims_over_orders(): void {
        // 4 Bestellungen im Zeitraum als Basis.
        for ($i = 0; $i < 4; $i++) {
            $this->makeOrder(PurchaseOrderStatus::Ordered, '2026-06-15', '2026-06-05', null);
        }
        // 1 Reklamation mit Lieferantenbezug im Zeitraum.
        $this->makeClaim('2026-06-20');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertTrue($card['complaints']['available']);
        $this->assertSame(1, $card['complaints']['count']);
        $this->assertSame(4, $card['complaints']['base']);
        $this->assertEqualsWithDelta(0.25, $card['complaints']['rate'], 0.0001);
        $this->assertSame(75, $card['complaints']['goodness']); // 100 - 25
    }

    public function test_complaint_rate_is_no_data_without_orders(): void {
        // Reklamation ohne Bestellbasis im Zeitraum.
        $this->makeClaim('2026-06-20');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertFalse($card['complaints']['available']);
        $this->assertNull($card['complaints']['rate']);
        $this->assertSame(1, $card['complaints']['count']);
        $this->assertSame(0, $card['complaints']['base']);
    }

    // ── Preisentwicklung ─────────────────────────────────────────────────

    public function test_price_trend_rising(): void {
        $this->makeOrder(PurchaseOrderStatus::Ordered, '2026-06-10', '2026-06-01', '10.00');
        $this->makeOrder(PurchaseOrderStatus::Ordered, '2026-06-20', '2026-06-15', '12.00');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertTrue($card['price']['available']);
        $this->assertSame('up', $card['price']['direction']);
        $this->assertEqualsWithDelta(20.0, $card['price']['trend_pct'], 0.0001);
        $this->assertSame(0, $card['price']['goodness']); // 50 - 20*2.5, geklemmt
    }

    public function test_price_trend_falling(): void {
        $this->makeOrder(PurchaseOrderStatus::Ordered, '2026-06-10', '2026-06-01', '20.00');
        $this->makeOrder(PurchaseOrderStatus::Ordered, '2026-06-20', '2026-06-15', '15.00');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertSame('down', $card['price']['direction']);
        $this->assertEqualsWithDelta(-25.0, $card['price']['trend_pct'], 0.0001);
        $this->assertSame(100, $card['price']['goodness']); // 50 - (-25)*2.5 → geklemmt auf 100
    }

    public function test_price_needs_two_points_per_article(): void {
        // Nur ein Preispunkt → keine Aussage über die Entwicklung.
        $this->makeOrder(PurchaseOrderStatus::Ordered, '2026-06-10', '2026-06-01', '10.00');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertFalse($card['price']['available']);
        $this->assertNull($card['price']['trend_pct']);
    }

    // ── „keine Daten" verfälscht den Gesamt-Score nicht ──────────────────

    public function test_missing_metrics_do_not_drag_overall_score(): void {
        // Nur eine ISMS-Bewertung (Low) — keine Einkaufs-/Reklamationsdaten.
        $this->makeAssessment(IncidentSeverity::Low, '2026-05-01');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertFalse($card['ontime']['available']);
        $this->assertFalse($card['complaints']['available']);
        $this->assertFalse($card['price']['available']);
        $this->assertTrue($card['quality']['available']);
        $this->assertSame(100, $card['quality']['goodness']);
        // Re-normalisiert über die EINE verfügbare Kennzahl → 100, nicht 100*0.20.
        $this->assertSame(100.0, $card['overall']);
    }

    // ── Gesamt-Score-Gewichtung ──────────────────────────────────────────

    public function test_overall_weighting_matches_manual_calculation(): void {
        // Termintreue 0.5 (goodness 50): 1 pünktlich + 1 verspätet.
        $this->makeReceivedOrder('2026-06-10', '2026-06-05', '2026-06-01', '10.00');
        $this->makeReceivedOrder('2026-06-10', '2026-06-20', '2026-06-15', '12.00');
        // Reklamationsquote 0.5 (goodness 50): 1 Reklamation / 2 Bestellungen.
        $this->makeClaim('2026-06-25');
        // Preistrend +20 % (goodness 0) aus denselben zwei Positionen (10 → 12).
        // ISMS High (goodness 33).
        $this->makeAssessment(IncidentSeverity::High, '2026-05-01');

        $card = $this->service->scorecard($this->supplier, $this->from, $this->to);

        $this->assertSame(50, $card['ontime']['goodness']);
        $this->assertSame(50, $card['complaints']['goodness']);
        $this->assertSame(0, $card['price']['goodness']);
        $this->assertSame(33, $card['quality']['goodness']);

        // (50*.35 + 50*.30 + 33*.20 + 0*.15) / (.35+.30+.20+.15)
        //  = (17.5 + 15 + 6.6 + 0) / 1.0 = 39.1
        $this->assertSame(39.1, $card['overall']);
    }

    public function test_ranking_sorts_by_overall_and_isolates_no_data_last(): void {
        // Guter Lieferant (pünktlich, keine Reklamation).
        $this->makeReceivedOrder('2026-06-10', '2026-06-05', '2026-06-01', '10.00');

        // Zweiter Lieferant im Ranking vorhanden (hat eine Bestellung), aber
        // ohne auswertbare Kennzahl im Zeitraum: Bestellung liegt VOR dem
        // Zeitraum (keine Reklamationsbasis), kein Wareneingang (keine
        // Termintreue), kein Preis, keine ISMS-Bewertung → Score null.
        $other = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->makeOrderFor($other, PurchaseOrderStatus::Ordered, '2026-05-10', '2026-05-01', null);

        $ranking = $this->service->ranking($this->from, $this->to);

        $this->assertSame($this->supplier->id, $ranking[0]['supplier']->id);
        // Der Lieferant ohne bewertbare Kennzahl steht mit Score null hinten.
        $last = end($ranking);
        $this->assertSame($other->id, $last['supplier']->id);
        $this->assertNull($last['overall']);
    }

    // ── Fixture-Helfer ───────────────────────────────────────────────────

    private function makeReceivedOrder(string $expectedAt, string $deliveredAt, string $orderedAt, string $unitPrice): PurchaseOrder {
        $order = $this->makeOrder(PurchaseOrderStatus::Received, $expectedAt, $orderedAt, $unitPrice);
        $line = $order->lines()->firstOrFail();
        $this->makeReceipt($line, $deliveredAt);

        return $order;
    }

    private function makeOrder(PurchaseOrderStatus $status, string $expectedAt, string $orderedAt, ?string $unitPrice): PurchaseOrder {
        return $this->makeOrderFor($this->supplier, $status, $expectedAt, $orderedAt, $unitPrice);
    }

    private function makeOrderFor(Supplier $supplier, PurchaseOrderStatus $status, string $expectedAt, string $orderedAt, ?string $unitPrice): PurchaseOrder {
        $order = PurchaseOrder::create([
            'organization_id' => $this->organization->id,
            'number' => 'BE-' . str_pad((string) (++$this->orderSeq), 4, '0', STR_PAD_LEFT),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => $status->value,
            'currency' => 'EUR',
            'ordered_at' => $orderedAt,
            'expected_at' => $expectedAt,
        ]);

        PurchaseOrderLine::create([
            'organization_id' => $this->organization->id,
            'purchase_order_id' => $order->id,
            'article_id' => $this->article->id,
            'article_variant_id' => $this->variant->id,
            'description' => 'Position',
            'ordered_qty' => '10',
            'received_qty' => $status === PurchaseOrderStatus::Received ? '10' : '0',
            'unit' => 'Stk',
            'unit_price' => $unitPrice,
            'currency' => 'EUR',
        ]);

        return $order;
    }

    private function makeReceipt(PurchaseOrderLine $line, string $occurredAt): StockMovement {
        return StockMovement::create([
            'organization_id' => $this->organization->id,
            'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_state' => StockState::Physical->value,
            'ownership_type' => OwnershipType::Own->value,
            'movement_type' => StockMovementType::Receipt->value,
            'qty_base' => '10',
            'occurred_at' => CarbonImmutable::parse($occurredAt),
            'source_type' => $line->getMorphClass(),
            'source_id' => $line->id,
            'currency' => 'EUR',
        ]);
    }

    private function makeClaim(string $reportedAt): ClaimCase {
        return ClaimCase::create([
            'organization_id' => $this->organization->id,
            'number' => 'RK-' . str_pad((string) (++$this->orderSeq), 4, '0', STR_PAD_LEFT),
            'status' => \App\Enums\Claims\ClaimStatus::Received->value,
            'source' => \App\Enums\Claims\ClaimSource::Internal->value,
            'priority' => 'normal',
            'severity' => 'minor',
            'title' => 'Mangel',
            'supplier_id' => $this->supplier->id,
            'reported_at' => CarbonImmutable::parse($reportedAt),
        ]);
    }

    private function makeAssessment(IncidentSeverity $risk, string $lastReview): IsmsSupplierAssessment {
        return IsmsSupplierAssessment::create([
            'organization_id' => $this->organization->id,
            'assessment_no' => ++$this->orderSeq,
            'supplier_id' => $this->supplier->id,
            'supplier_name' => $this->supplier->name,
            'criticality' => IncidentSeverity::Medium->value,
            'has_nda' => false,
            'has_dpa' => false,
            'audit_right' => false,
            'last_review_on' => $lastReview,
            'risk_rating' => $risk->value,
            'status' => SupplierAssessmentStatus::Approved->value,
        ]);
    }
}
