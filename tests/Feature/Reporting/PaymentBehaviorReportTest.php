<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentBehaviorReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Models\{Customer, Invoice, LexofficeVoucher, User};
use App\Services\Reporting\PaymentBehaviorReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Zahlungsverhalten & Forderungstrend (MVP-468): DSO-Countback, Zahldauer,
 * Pünktlichkeitsquote und überfällige offene Rechnungen — Stichtag ist das
 * Zeitraumende; Entwürfe und Proforma zählen nicht.
 *
 * Fixture (Zeitraum 2030-01-01 – 2030-03-31):
 *  - Alpha R-1: 10.01. → fällig 24.01., bezahlt 20.01. (10 Tage, pünktlich, 1000 €)
 *  - Beta  R-2: 05.01. → fällig 19.01., bezahlt 08.02. (34 Tage, 20 Tage Verzug, 500 €)
 *  - Beta  R-3: 01.02. → fällig 15.02., offen (überfällig 44 Tage, 500 €)
 *  - Entwurf + Proforma dürfen nirgends einfließen.
 */
class PaymentBehaviorReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $alpha = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Alpha GmbH']);
        $beta = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Beta AG']);

        $this->createInvoice($alpha, 'R-1', Invoice::STATUS_PAID, '2030-01-10', '2030-01-24', '2030-01-20', 1000);
        $this->createInvoice($beta, 'R-2', Invoice::STATUS_PAID, '2030-01-05', '2030-01-19', '2030-02-08', 500);
        $this->createInvoice($beta, 'R-3', Invoice::STATUS_ISSUED, '2030-02-01', '2030-02-15', null, 500);
        // Störer: Entwurf und Proforma bleiben unberücksichtigt.
        $this->createInvoice($alpha, 'R-4', Invoice::STATUS_DRAFT, '2030-02-10', '2030-02-24', null, 9999);
        $this->createInvoice($alpha, 'P-1', Invoice::STATUS_ISSUED, '2030-02-12', '2030-02-26', null, 8888, Invoice::TYPE_PROFORMA);
    }

    private function createInvoice(Customer $customer, string $number, string $status, string $issued, string $due, ?string $paid, float $total, string $type = Invoice::TYPE_INVOICE): void {
        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => $number,
            'status' => $status,
            'type' => $type,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'issued_on' => $issued,
            'due_on' => $due,
            'paid_on' => $paid,
            'total' => $total,
            'created_by' => $this->admin->id,
        ]);
    }

    private function build(): array {
        return app(PaymentBehaviorReportBuilder::class)->build(
            CarbonImmutable::parse('2030-01-01'),
            CarbonImmutable::parse('2030-03-31'),
            null,
        );
    }

    public function test_kpis_cover_dso_pay_days_and_overdue(): void {
        $result = $this->build();
        $kpis = $result['kpis'];

        $this->assertTrue($result['hasData']);
        // DSO: offen 500 ÷ Umsatz 2000 der letzten 90 Tage × 90.
        $this->assertSame(22.5, $kpis['dso']);
        $this->assertSame(22.0, $kpis['avgPayDays']);
        $this->assertSame(50.0, $kpis['onTimeShare']);
        $this->assertSame(2, $kpis['paidCount']);
        $this->assertSame(1, $kpis['overdueCount']);
        $this->assertSame(500.0, $kpis['overdueTotal']);

        $this->assertCount(1, $result['overdue']);
        $this->assertSame('R-3', $result['overdue'][0]['number']);
        $this->assertSame(44, $result['overdue'][0]['daysOverdue']);
    }

    public function test_monthly_series_uses_month_end_countback(): void {
        $monthly = collect($this->build()['monthly'])->keyBy('month');

        // Januar: R-2 (bezahlt erst im Februar) ist offen → 500 ÷ 1500 × 90.
        $this->assertSame(30.0, $monthly['2030-01']['dso']);
        $this->assertSame(10.0, $monthly['2030-01']['avgPayDays']);
        $this->assertSame(22.5, $monthly['2030-02']['dso']);
        $this->assertSame(34.0, $monthly['2030-02']['avgPayDays']);
        $this->assertNull($monthly['2030-03']['avgPayDays']);
    }

    public function test_pay_box_and_delay_top(): void {
        $result = $this->build();

        // Nur die Gesamtzeile: je Kunde sind es unter 3 bezahlte Rechnungen.
        $this->assertCount(1, $result['payBox']);
        $box = $result['payBox'][0];
        $this->assertSame(10.0, $box['min']);
        $this->assertSame(22.0, $box['median']);
        $this->assertSame(34.0, $box['max']);
        $this->assertSame(2, $box['n']);

        $this->assertSame('Beta AG', $result['delayTop'][0]['customerName']);
        $this->assertSame(20.0, $result['delayTop'][0]['avgDelay']);
    }

    public function test_report_requires_report_view_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-03-31'))
            ->get(route('reports.payment-behavior'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-03-31'))
            ->get(route('reports.payment-behavior'))
            ->assertOk()
            ->assertSee('R-3');
    }

    public function test_mirrored_lexoffice_vouchers_flow_into_payment_behavior(): void {
        // Phase-54-Nachtrag: Zahlungsverhalten funktioniert auch bei externer
        // Rechnungshoheit — Spiegelbelege mit paid_date aus der
        // Payments-Anreicherung zählen wie lokale Rechnungen.
        $gamma = Customer::create(['organization_id' => $this->organization->id, 'name' => 'Gamma Extern GmbH']);
        $mk = function (string $ext, string $number, string $type, string $status, string $date, ?string $due, ?string $paid, float $total) use ($gamma): void {
            LexofficeVoucher::create([
                'organization_id' => $this->organization->id,
                'external_id' => $ext,
                'customer_id' => $gamma->id,
                'voucher_type' => $type,
                'voucher_status' => $status,
                'voucher_number' => $number,
                'voucher_date' => $date,
                'due_date' => $due,
                'paid_date' => $paid,
                'total_amount' => $total,
                'currency' => 'EUR',
            ]);
        };

        $mk('v1', 'LX-A', 'invoice', 'paid', '2030-01-08', '2030-01-22', '2030-01-18', 200.0); // 10 Tage, pünktlich
        $mk('v2', 'LX-B', 'invoice', 'open', '2030-02-05', '2030-02-19', null, 300.0);         // überfällig 40 Tage
        $mk('v3', 'LX-C', 'invoice', 'paid', '2030-01-10', '2030-01-24', null, 150.0);         // bezahlt ohne Zahldatum
        $mk('v4', 'R-1', 'invoice', 'paid', '2030-01-10', '2030-01-24', '2030-01-25', 1000.0); // Duplikat der lokalen R-1
        $mk('v5', 'LX-D', 'creditnote', 'paid', '2030-01-12', null, '2030-01-20', 50.0);       // Gutschrift zählt hier nicht

        $result = $this->build();
        $kpis = $result['kpis'];

        // R-1 (10 T), R-2 (34 T) lokal + LX-A (10 T) — Duplikat/Gutschrift/
        // ohne-Zahldatum erhöhen den Zähler NICHT.
        $this->assertSame(3, $kpis['paidCount']);
        $this->assertSame(18.0, $kpis['avgPayDays']);
        $this->assertSame(66.7, $kpis['onTimeShare']);

        // Überfällig: lokale R-3 (44 Tage) + gespiegelte LX-B (40 Tage).
        $this->assertSame(2, $kpis['overdueCount']);
        $this->assertSame(800.0, $kpis['overdueTotal']);
        $byNumber = collect($result['overdue'])->keyBy('number');
        $this->assertSame(40, $byNumber['LX-B']['daysOverdue']);
        $this->assertNull($byNumber['LX-B']['invoiceId']);
        $this->assertNotNull($byNumber['R-3']['invoiceId']);

        // Zahldauer-Verteilung über beide Quellen (n = 3, Median 10 Tage).
        $this->assertSame(3, $result['payBox'][0]['n']);
        $this->assertSame(10.0, $result['payBox'][0]['median']);
    }

    public function test_customer_charts_link_to_self_filtered_report(): void {
        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeSession('2030-01-01', '2030-03-31'))
            ->get(route('reports.payment-behavior'));

        // Verzugs-Top verlinkt auf den kundengefilterten Selbst-Drilldown (MVP-470).
        $delay = $response->viewData('delaySeries');
        $this->assertStringContainsString('reports/payment-behavior?', $delay[0]['url']);
        $this->assertStringContainsString('customer=', $delay[0]['url']);

        // Boxplot: Gesamtzeile ohne Link, Kundenzeilen — hier n<3 — entfallen.
        $payBox = $response->viewData('payBox');
        $this->assertNull($payBox[0]['url']);
    }
}
