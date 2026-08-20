<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficePaymentSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{LexofficeVoucher, PluginSetting};
use App\Plugins\Contracts\{PaymentSyncer, PluginCapability};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * PaymentSync-Capability (Audit 2026-08, Welle 1.5): Lexoffice liest das
 * Zahldatum bezahlter Belege zurück und deklariert das jetzt auch als
 * Fähigkeit — vorher war der Enum-Case ohne Implementierer (tot), obwohl
 * die Funktion im VoucherSync längst existierte.
 */
class LexofficePaymentSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'lex-key'],
        ]);
    }

    public function test_plugin_advertises_payment_sync_and_implements_the_contract(): void {
        $plugin = app(LexofficePlugin::class);

        $this->assertInstanceOf(PaymentSyncer::class, $plugin);
        $this->assertContains(PluginCapability::PaymentSync, $plugin->capabilities());
    }

    public function test_capability_lookup_finds_the_plugin(): void {
        // Vorher lieferte withCapability(PaymentSync) IMMER eine leere Menge.
        $found = app(PluginManager::class)->withCapability(PluginCapability::PaymentSync);

        $this->assertTrue($found->contains(fn ($plugin): bool => $plugin->id() === LexofficePlugin::ID));
    }

    public function test_sync_payments_enriches_paid_dates(): void {
        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id,
            'external_id' => 'lex-voucher-1',
            'voucher_type' => 'salesinvoice',
            'voucher_status' => 'paid',
            'voucher_date' => '2026-08-01',
            'paid_date' => null,
        ]);

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/payments/lex-voucher-1' => FakePluginHttp::response(['paidDate' => '2026-08-05T00:00:00.000+02:00']),
        ]);

        $result = app(LexofficePlugin::class)->syncPayments($this->organization);

        $this->assertSame(1, $result['paid_dates']);
        $this->assertSame('2026-08-05', LexofficeVoucher::query()->firstOrFail()->paid_date?->format('Y-m-d'));
    }

    public function test_sync_payments_without_api_key_reports_error_without_calls(): void {
        // Über das Model, nicht per Query-Update: die settings-Spalte ist
        // verschlüsselt gecastet — ein Builder-Update schriebe Rohtext hinein.
        PluginSetting::query()->where('plugin_id', LexofficePlugin::ID)
            ->firstOrFail()
            ->forceFill(['settings' => ['api_key' => null]])
            ->save();
        $fake = FakePluginHttp::fake();

        $result = app(LexofficePlugin::class)->syncPayments($this->organization);

        $this->assertSame(0, $result['paid_dates']);
        $this->assertArrayHasKey('error', $result);
        $fake->assertNothingSent();
    }
}
