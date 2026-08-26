<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlVoucherPullTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\JtlWawi;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Models\Finance\AccountingVoucher;
use App\Models\JtlConnection;
use App\Plugins\JtlWawi\Services\JtlVoucherPullService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-731 (Vollscan G18): Beleg-Rückabruf aus JTL-Wawi.
 *
 * Aufgezeichnete Beispielantwort im JTL-Standard-Envelope (items/hasNextPage).
 * Zweiter, ebenso wichtiger Fall: Instanzen, deren API-Stand die
 * Rechnungsliste gar nicht anbietet (404/405) — dann wird NICHTS gespiegelt,
 * statt eine andere Ressource als Rechnung auszugeben.
 */
final class JtlVoucherPullTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://wawi.example.test:5883/api/eazybusiness';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        JtlConnection::query()->create([
            'organization_id' => $this->organization->id,
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'base_url' => self::BASE,
            'api_version' => '2.0',
            'allow_private_network' => true,
            'api_key' => 'KEY-TEST',
            'status' => JtlConnection::STATUS_ACTIVE,
        ]);
    }

    /** @return array<string, mixed> */
    private function fixture(): array {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents(base_path('tests/Fixtures/Plugins/JtlWawi/salesinvoices.page1.json')), true);

        return $data;
    }

    public function test_maps_sales_invoices_and_detects_cancellation(): void {
        $fake = FakePluginHttp::fake([
            self::BASE . '/v2/salesinvoices*' => FakePluginHttp::response($this->fixture()),
        ]);

        $result = app(JtlVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(['read' => 2, 'created' => 2, 'updated' => 0, 'skipped' => 0], $result);
        $fake->assertSent(fn (RequestInterface $r): bool => str_contains((string) $r->getUri(), 'pageNumber=1'));

        $invoice = AccountingVoucher::query()->where('external_id', '88001')->firstOrFail();
        $this->assertSame('jtl_wawi', $invoice->plugin_id);
        $this->assertSame(DocumentDirection::Outgoing->value, $invoice->direction);
        $this->assertSame(DocumentKind::Invoice->value, $invoice->document_kind);
        $this->assertSame('open', $invoice->voucher_state);
        $this->assertSame('238.00', (string) $invoice->total_amount);
        $this->assertSame('RE-88001', $invoice->voucher_number);

        // Storno: Kennzeichen ODER negativer Bruttobetrag.
        $storno = AccountingVoucher::query()->where('external_id', '88002')->firstOrFail();
        $this->assertTrue($storno->is_cancellation);
        $this->assertSame(DocumentKind::Cancellation->value, $storno->document_kind);
        $this->assertSame('cancelled', $storno->voucher_state);
        $this->assertSame('88001', $storno->cancels_external_id);
    }

    public function test_second_run_uses_created_since_and_updates_instead_of_duplicating(): void {
        FakePluginHttp::fake([self::BASE . '/v2/salesinvoices*' => FakePluginHttp::response($this->fixture())]);
        app(JtlVoucherPullService::class)->pull((int) $this->organization->id);

        $fake = FakePluginHttp::fake([self::BASE . '/v2/salesinvoices*' => FakePluginHttp::response($this->fixture())]);
        $result = app(JtlVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['updated']);
        $this->assertSame(2, AccountingVoucher::query()->count());
        $fake->assertSent(fn (RequestInterface $r): bool => str_contains(urldecode((string) $r->getUri()), 'createdSince=2026-08-06'));
    }

    public function test_missing_endpoint_mirrors_nothing_instead_of_guessing(): void {
        FakePluginHttp::fake([
            self::BASE . '/v2/salesinvoices*' => FakePluginHttp::response(['errorCode' => 'NotFound'], 404),
        ]);

        $result = app(JtlVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(0, $result['read']);
        $this->assertSame(0, AccountingVoucher::query()->count());
    }

    public function test_without_active_connection_nothing_is_requested(): void {
        JtlConnection::query()->update(['status' => JtlConnection::STATUS_BLOCKED]);
        $fake = FakePluginHttp::fake([self::BASE . '/*' => FakePluginHttp::response([])]);

        $result = app(JtlVoucherPullService::class)->pull((int) $this->organization->id);

        $this->assertSame(0, $result['read']);
        $fake->assertNothingSent();
    }
}
