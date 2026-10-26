<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskVoucherPullTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\SevDesk;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\PluginSetting;
use App\Models\Supplier\Supplier;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-611/731: Beleg-Rückabruf aus sevDesk auf dem gemeinsamen
 * VoucherPuller-Vertrag — inkl. der normalisierten Spalten (Richtung,
 * Belegart, Zustand) und des zentralen Kommandos `accounting:pull-vouchers`.
 */
class SevDeskVoucherPullTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const VOUCHERS = 'https://my.sevdesk.de/api/v1/Voucher*';

    private const INVOICES = 'https://my.sevdesk.de/api/v1/Invoice*';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => SevDeskPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'tok-123'],
        ]);
    }

    /** @return array<string, mixed> */
    private function fixture(): array {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents(base_path('tests/Fixtures/Plugins/SevDesk/vouchers.json')), true);

        return $data;
    }

    public function test_maps_direction_state_and_supplier(): void {
        Supplier::create([
            'organization_id' => $this->organization->id,
            'name' => 'Bueromarkt AG',
        ]);

        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture()), self::INVOICES => FakePluginHttp::response(['objects' => []])]);

        $puller = app(VoucherPullerRegistry::class)->find(SevDeskPlugin::ID);
        $this->assertNotNull($puller);
        $result = $puller->pull((int) $this->organization->id);

        $this->assertSame(2, $result['created']);

        // D = Ausgabe → eingehend, Status 1000 = bezahlt.
        $expense = AccountingVoucher::query()->where('external_id', '77001')->firstOrFail();
        $this->assertSame(DocumentDirection::Incoming->value, $expense->direction);
        $this->assertSame(DocumentKind::Other->value, $expense->document_kind);
        $this->assertSame(AccountingVoucherState::Paid, $expense->voucher_state);
        $this->assertSame('59.50', (string) $expense->total_amount);
        $this->assertNotNull($expense->supplier_id);
        $this->assertFalse($expense->is_cancellation);

        // C = Einnahme → ausgehend, Status 100 = offen.
        $income = AccountingVoucher::query()->where('external_id', '77002')->firstOrFail();
        $this->assertSame(DocumentDirection::Outgoing->value, $income->direction);
        $this->assertSame(AccountingVoucherState::Open, $income->voucher_state);
        $this->assertNull($income->supplier_id);
    }

    public function test_invoices_are_mirrored_with_their_positions_and_recurring_invoices_as_plan(): void {
        // `GET /Invoice` (Feature 185, MVP-1425): RE = Rechnung mit Status 200 offen; WKR = Serienrechnung als Plan (accountIntervall ISO-8601).
        FakePluginHttp::fake([
            self::VOUCHERS => FakePluginHttp::response(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Invoice/9001/getPositions*' => FakePluginHttp::response(['objects' => [
                ['id' => '1', 'positionNumber' => '0', 'name' => 'Microsoft 365 Business Premium', 'text' => 'Jahreslizenz', 'quantity' => 2, 'priceNet' => '247.2', 'sumNetAccounting' => '494.4', 'taxRate' => '19', 'part' => ['id' => '555', 'objectName' => 'Part'], 'unity' => ['id' => '1', 'objectName' => 'Unity', 'name' => 'Stück']],
            ]]),
            self::INVOICES => FakePluginHttp::response(['objects' => [
                ['id' => '9001', 'objectName' => 'Invoice', 'invoiceNumber' => 'RE-1001', 'invoiceType' => 'RE', 'status' => '200', 'invoiceDate' => '2026-09-03 00:00:00', 'sumNet' => '494.4', 'sumGross' => '588.34', 'currency' => 'EUR', 'contact' => ['id' => '42', 'objectName' => 'Contact'], 'update' => '2026-09-03 10:00:00'],
                ['id' => '9002', 'objectName' => 'Invoice', 'invoiceNumber' => 'WKR-7', 'invoiceType' => 'WKR', 'status' => '200', 'invoiceDate' => '2025-09-03 00:00:00', 'accountIntervall' => 'P1Y', 'accountNextInvoice' => 1788652800, 'currency' => 'EUR', 'contact' => ['id' => '42', 'objectName' => 'Contact']],
            ]]),
        ]);
        $puller = app(VoucherPullerRegistry::class)->find(SevDeskPlugin::ID);
        $this->assertInstanceOf(\App\Services\Finance\Accounting\Vouchers\LineAwareVoucherPuller::class, $puller);
        $result = $puller->pull((int) $this->organization->id);
        $this->assertSame(2, $result['created']);

        $invoice = AccountingVoucher::query()->where('external_id', 'invoice:9001')->firstOrFail();
        $this->assertSame(DocumentDirection::Outgoing->value, $invoice->direction);
        $this->assertSame(DocumentKind::Invoice->value, $invoice->document_kind);
        $this->assertSame(AccountingVoucherState::Open, $invoice->voucher_state);
        $this->assertSame('494.40', (string) $invoice->net_amount);
        $this->assertNull($invoice->lines_synced_at, 'Positionen kommen erst im Einzelabruf');
        $lines = $puller->pullLines((int) $this->organization->id, $invoice);
        $this->assertCount(1, $lines);
        $this->assertSame(['Microsoft 365 Business Premium', 2.0, '247.2000', '494.40', '555', 'Stück'], [$lines[0]->name, $lines[0]->quantity, $lines[0]->unitNet, $lines[0]->totalNet, $lines[0]->externalArticleId, $lines[0]->unitName]);

        $plan = AccountingVoucher::query()->where('external_id', 'invoice:9002')->firstOrFail();
        $this->assertSame(DocumentKind::RecurringTemplate->value, $plan->document_kind);
        $this->assertSame(DocumentDirection::Neutral->value, $plan->direction);
        $this->assertSame(['ACTIVE', 'ANNUALLY', '2026-09-06'], [data_get($plan->payload, 'plan.status'), data_get($plan->payload, 'plan.interval'), data_get($plan->payload, 'plan.next_execution_on')]);

        // Der Belegabruf lädt die Positionen nach und stempelt den Beleg.
        $this->artisan('accounting:pull-vouchers', ['--organization' => $this->organization->id])->assertSuccessful();
        $this->assertNotNull($invoice->fresh()?->lines_synced_at);
        $this->assertSame(1, $invoice->lines()->count());
    }

    public function test_command_pulls_configured_providers_without_duplicating(): void {
        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture()), self::INVOICES => FakePluginHttp::response(['objects' => []])]);

        $this->artisan('accounting:pull-vouchers', ['--organization' => $this->organization->id])
            ->assertSuccessful();
        $this->artisan('accounting:pull-vouchers', ['--organization' => $this->organization->id])
            ->assertSuccessful();

        $this->assertSame(2, AccountingVoucher::query()->count());
    }

    public function test_command_rejects_an_unknown_provider(): void {
        $this->artisan('accounting:pull-vouchers', ['--plugin' => 'nope'])->assertFailed();
    }
}
