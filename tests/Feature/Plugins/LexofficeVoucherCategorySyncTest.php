<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherCategorySyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Plugins\Lexoffice\{LexofficePostingCategory, LexofficeVoucher, LexofficeVoucherCategory};
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\{LexofficePlugin, LexofficeVoucherCategorySync};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/** MVP-905: Kategoriezeilen der Einkaufsbelege und Ausgaben je Kategorie. */
final class LexofficeVoucherCategorySyncTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function voucher(string $externalId, string $type, int $supplierId, string $status = 'paid'): LexofficeVoucher {
        return LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'supplier_id' => $supplierId,
            'voucher_type' => $type, 'voucher_status' => $status, 'voucher_number' => $externalId,
            'voucher_date' => '2026-06-15', 'total_amount' => 119, 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    public function test_purchase_vouchers_are_split_by_posting_category_and_shown_in_the_report(): void {
        $supplier = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Bürobedarf AG']);
        $invoice = $this->voucher('pv-1', 'purchaseinvoice', $supplier->id);
        $credit = $this->voucher('pc-1', 'purchasecreditnote', $supplier->id);
        $this->voucher('pv-draft', 'purchaseinvoice', $supplier->id, 'draft');

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/posting-categories' => FakePluginHttp::response([
                ['id' => 'cat-office', 'name' => 'Bürobedarf', 'type' => 'outgo', 'groupName' => 'Büro'],
                ['id' => 'cat-fuel', 'name' => 'Kraftstoff', 'type' => 'outgo', 'groupName' => 'Kfz'],
            ]),
            'https://api.lexoffice.io/v1/vouchers/pv-1' => FakePluginHttp::response(['taxType' => 'gross', 'voucherItems' => [
                ['amount' => 119.0, 'taxAmount' => 19.0, 'taxRatePercent' => 19, 'categoryId' => 'cat-office'],
                ['amount' => 59.5, 'taxAmount' => 9.5, 'taxRatePercent' => 19, 'categoryId' => 'cat-fuel'],
            ]]),
            'https://api.lexoffice.io/v1/vouchers/pc-1' => FakePluginHttp::response(['taxType' => 'net', 'voucherItems' => [
                ['amount' => 20.0, 'taxAmount' => 3.8, 'taxRatePercent' => 19, 'categoryId' => 'cat-office'],
            ]]),
        ]);

        $sync = (new LexofficeVoucherCategorySync('key'))->withoutThrottle();
        $this->assertSame(['synced' => 2, 'failed' => 0, 'remaining' => 0], $sync->syncMissing($this->organization));
        $this->assertSame(2, LexofficePostingCategory::query()->count());
        $this->assertSame(['100.00', '50.00'], LexofficeVoucherCategory::query()->where('voucher_id', $invoice->id)->orderBy('position')->get()->map(fn ($r) => $r->net_amount->getAmount())->all());
        $this->assertSame('20.00', LexofficeVoucherCategory::query()->where('voucher_id', $credit->id)->firstOrFail()->net_amount->getAmount());
        $this->assertNotNull($invoice->fresh()->categories_synced_at);
        $this->assertSame(0, $sync->pending($this->organization->id)->count(), 'Entwurf bleibt außen vor');

        $report = $this->actingAs($this->orgAdmin())->withSession($this->dateRangeMonth(2026, 6))->get(route('reports.suppliers'));
        $report->assertOk();
        $series = $report->viewData('categorySpend');
        $this->assertSame(['Bürobedarf', 'Kraftstoff'], array_column($series['bands'], 'label'));
        $june = collect($series['series'])->firstWhere(fn ($p) => ($p['c0'] ?? 0) !== 0.0);
        $this->assertSame([80.0, 50.0], [$june['c0'], $june['c1']], 'Bürobedarf 100 − 20 Gutschrift, Kraftstoff 50');
    }

    public function test_backfill_command_loads_missing_categories(): void {
        $supplier = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Werkstattbedarf']);
        $voucher = $this->voucher('pv-9', 'purchaseinvoice', $supplier->id);
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/posting-categories' => FakePluginHttp::response([['id' => 'cat-tools', 'name' => 'Werkzeug', 'type' => 'outgo']]),
            'https://api.lexoffice.io/v1/vouchers/pv-9' => FakePluginHttp::response(['taxType' => 'net', 'voucherItems' => [['amount' => 40.0, 'taxAmount' => 7.6, 'categoryId' => 'cat-tools']]]),
        ]);

        $this->artisan('lexoffice:sync-voucher-categories', ['--limit' => 5])
            ->expectsOutputToContain('1 Belege, 0 Fehler, 0 offen')
            ->assertSuccessful();
        $this->assertSame('40.00', LexofficeVoucherCategory::query()->where('voucher_id', $voucher->id)->firstOrFail()->net_amount->getAmount());
    }
}
