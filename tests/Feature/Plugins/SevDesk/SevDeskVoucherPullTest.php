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
use App\Models\Finance\AccountingVoucher;
use App\Models\{PluginSetting, Supplier};
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

        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture())]);

        $puller = app(VoucherPullerRegistry::class)->find(SevDeskPlugin::ID);
        $this->assertNotNull($puller);
        $result = $puller->pull((int) $this->organization->id);

        $this->assertSame(2, $result['created']);

        // D = Ausgabe → eingehend, Status 1000 = bezahlt.
        $expense = AccountingVoucher::query()->where('external_id', '77001')->firstOrFail();
        $this->assertSame(DocumentDirection::Incoming->value, $expense->direction);
        $this->assertSame(DocumentKind::Other->value, $expense->document_kind);
        $this->assertSame('paid', $expense->voucher_state);
        $this->assertSame('59.50', (string) $expense->total_amount);
        $this->assertNotNull($expense->supplier_id);
        $this->assertFalse($expense->is_cancellation);

        // C = Einnahme → ausgehend, Status 100 = offen.
        $income = AccountingVoucher::query()->where('external_id', '77002')->firstOrFail();
        $this->assertSame(DocumentDirection::Outgoing->value, $income->direction);
        $this->assertSame('open', $income->voucher_state);
        $this->assertNull($income->supplier_id);
    }

    public function test_command_pulls_configured_providers_without_duplicating(): void {
        FakePluginHttp::fake([self::VOUCHERS => FakePluginHttp::response($this->fixture())]);

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
