<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductRevenueReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\{Article, Customer, Invoice, InvoiceItem, Organization, User};
use App\Services\Reporting\ProductRevenueReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Umsatz je Produkt (Feature 140, MVP-705): Aggregation je Artikel,
 * Sammelposten ohne Artikelbezug, Status-/Zeitraumfilter, Mandantengrenze,
 * Recht wie Abrechnungsbericht, CSV/PDF.
 */
class ProductRevenueReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Article $screw;

    private Article $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Alpha GmbH',
            'created_by' => $this->admin->id,
        ]);
        $this->screw = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'A-1', 'name' => 'Schraube M8', 'base_unit' => 'Stk']);
        $this->service = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'D-1', 'name' => 'Montage', 'base_unit' => 'Std.']);

        // Ausgestellt: Schraube 10 × 2, Montage 2 × 100, ohne Artikel 1 × 50.
        $this->invoice(Invoice::STATUS_ISSUED, '2030-06-10', [
            [$this->screw->id, '10', '2.00'],
            [$this->service->id, '2', '100.00'],
            [null, '1', '50.00'],
        ]);
        // Bezahlt: Schraube 5 × 2.
        $this->invoice(Invoice::STATUS_PAID, '2030-06-20', [[$this->screw->id, '5', '2.00']]);
        // Entwurf/Storno zählen nicht.
        $this->invoice(Invoice::STATUS_DRAFT, '2030-06-15', [[$this->screw->id, '100', '2.00']]);
        $this->invoice(Invoice::STATUS_CANCELLED, '2030-06-15', [[$this->screw->id, '100', '2.00']]);
        // Außerhalb des Zeitraums.
        $this->invoice(Invoice::STATUS_ISSUED, '2030-07-05', [[$this->screw->id, '100', '2.00']]);
    }

    /** @param list<array{0: ?int, 1: string, 2: string}> $items [article_id, quantity, unit_price] */
    private function invoice(string $status, string $issuedOn, array $items, ?int $organizationId = null): Invoice {
        $orgId = $organizationId ?? (int) $this->organization->id;
        $invoice = Invoice::create([
            'organization_id' => $orgId,
            'customer_id' => $this->customer->id,
            'number' => 'RE-' . fake()->unique()->numerify('#####'),
            'status' => $status,
            'type' => Invoice::TYPE_INVOICE,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'issued_on' => $issuedOn,
            'created_by' => $this->admin->id,
        ]);
        foreach ($items as $i => [$articleId, $qty, $price]) {
            InvoiceItem::create([
                'organization_id' => $orgId,
                'invoice_id' => $invoice->id,
                'article_id' => $articleId,
                'description' => 'Pos ' . ($i + 1),
                'quantity' => $qty,
                'unit' => 'Stk',
                'unit_price' => $price,
                'position' => $i + 1,
            ]);
        }

        return $invoice;
    }

    /** @return array{rows: list<array{articleId: ?int, number: ?string, name: string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int}>, total: float, withoutArticle: float, articleCount: int} */
    private function build(string $from = '2030-06-01', string $to = '2030-06-30'): array {
        return app(ProductRevenueReportBuilder::class)->build(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
    }

    /**
     * @param  list<array{articleId: ?int, number: ?string, name: string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int}>  $rows
     * @return array<string, array{articleId: ?int, number: ?string, name: string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int}>
     */
    private function byName(array $rows): array {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = $row;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function getWithRange(array $params = [], ?User $as = null): TestResponse {
        return $this->actingAs($as ?? $this->admin)
            ->withSession($this->dateRangeMonth(2030, 6))
            ->get(route('reports.product-revenue', $params));
    }

    public function test_aggregates_quantity_net_and_share_per_article(): void {
        $result = $this->build();
        $byName = $this->byName($result['rows']);

        $this->assertSame(280.0, $result['total']);
        $this->assertSame(2, $result['articleCount']);
        $this->assertSame(15.0, $byName['Schraube M8']['quantity']);
        $this->assertSame(30.0, $byName['Schraube M8']['net']);
        $this->assertSame(10.7, $byName['Schraube M8']['share']);
        $this->assertSame(2, $byName['Schraube M8']['invoices']);
        $this->assertSame('A-1', $byName['Schraube M8']['number']);
        $this->assertSame(200.0, $byName['Montage']['net']);
        $this->assertSame(71.4, $byName['Montage']['share']);
        // Umsatzstärkster zuerst, Sammelposten zuletzt.
        $this->assertSame(['Montage', 'Schraube M8', __('ohne Artikelbezug')], array_column($result['rows'], 'name'));
    }

    public function test_items_without_article_are_bundled(): void {
        $result = $this->build();
        $bucket = collect($result['rows'])->firstWhere('articleId', null);

        $this->assertNotNull($bucket);
        $this->assertSame(50.0, $bucket['net']);
        $this->assertSame(50.0, $result['withoutArticle']);
        $this->assertNull($bucket['number']);
    }

    public function test_period_filter_applies_to_issue_date(): void {
        $july = $this->build('2030-07-01', '2030-07-31');
        $byName = $this->byName($july['rows']);

        $this->assertSame(200.0, $july['total']);
        $this->assertSame(100.0, $byName['Schraube M8']['quantity']);
        $this->assertArrayNotHasKey('Montage', $byName);
    }

    public function test_other_organizations_are_excluded(): void {
        $other = Organization::factory()->create();
        $foreignArticle = Article::factory()->create(['organization_id' => $other->id, 'name' => 'Fremdartikel']);
        $this->invoice(Invoice::STATUS_ISSUED, '2030-06-12', [[$foreignArticle->id, '9', '9.00']], (int) $other->id);

        $result = $this->build();

        $this->assertSame(280.0, $result['total']);
        $this->assertNull(collect($result['rows'])->firstWhere('name', 'Fremdartikel'));
    }

    public function test_route_is_gated_like_billing_report(): void {
        $this->getWithRange()->assertOk()->assertSee('Schraube M8');

        $plain = $this->orgUser();
        $this->getWithRange([], $plain)->assertForbidden();

        $accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->getWithRange([], $accountant)->assertOk();
    }

    public function test_chart_and_full_height_table_are_rendered(): void {
        $response = $this->getWithRange(['top_n' => 5]);
        $response->assertOk()
            ->assertSee('<figure', false)
            ->assertSee('Montage')
            ->assertSee(__('ohne Artikelbezug'))
            ->assertSee('wd-table-flex', false);
    }

    public function test_csv_and_pdf_export(): void {
        $csv = $this->getWithRange(['export' => 'csv']);
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $body = (string) $csv->getContent();
        $this->assertStringContainsString('Artikelnummer', $body);
        $this->assertStringContainsString('Schraube M8', $body);
        $this->assertStringContainsString('200.00', $body);
        $this->assertStringContainsString(__('ohne Artikelbezug'), $body);

        $pdf = $this->getWithRange(['export' => 'pdf']);
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }
}
