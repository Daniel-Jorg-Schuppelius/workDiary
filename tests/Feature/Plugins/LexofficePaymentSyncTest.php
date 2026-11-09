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

use App\Models\Platform\PluginSetting;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\Services\LexofficeVoucherSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Zahldatum bezahlter Lexoffice-Belege (Audit 2026-08, Welle 1.5): der
 * Belegabgleich holt es über den Payments-Endpunkt nach. Die frühere
 * Plugin-Fähigkeit `PaymentSync` hatte keinen Verbraucher und ist entfallen
 * (MVP-1519); die Funktion bleibt im {@see LexofficeVoucherSync}.
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

    public function test_voucher_sync_enriches_paid_dates(): void {
        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id,
            'external_id' => 'lex-voucher-1',
            'voucher_type' => 'salesinvoice',
            'voucher_status' => 'paid',
            'voucher_date' => '2026-08-01',
            'paid_date' => null,
        ]);

        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/payments/lex-voucher-1' => FakePluginHttp::response(['paidDate' => '2026-08-05T00:00:00.000+02:00']),
        ]);

        $this->assertSame(1, (new LexofficeVoucherSync($this->organization->id))->enrichPaidDates($this->organization->id));
        $this->assertSame('2026-08-05', LexofficeVoucher::query()->firstOrFail()->paid_date?->format('Y-m-d'));
        $fake->assertSentCount(1);
        $this->assertSame('GET https://api.lexware.io/v1/payments/lex-voucher-1', $fake->recorded()[0]['request']->getMethod() . ' ' . $fake->recorded()[0]['request']->getUri());
    }

    /** PaymentsEndpoint: ein Fehler oder eine Antwort, die das SDK nicht abbilden kann, kostet nur diesen Beleg. */
    /** „irrecoverableReceivable“ fehlt im Enum PaymentItemType des SDK; das Zahldatum kommt trotzdem an. */
    public function test_voucher_sync_reads_paid_date_even_for_payment_items_the_sdk_does_not_know(): void {
        foreach (['lex-a' => '2026-08-02', 'lex-b' => '2026-08-01', 'lex-c' => '2026-07-31'] as $id => $date) {
            LexofficeVoucher::query()->create([
                'organization_id' => $this->organization->id,
                'external_id' => $id,
                'voucher_type' => 'salesinvoice',
                'voucher_status' => 'paid',
                'voucher_date' => $date,
                'paid_date' => null,
            ]);
        }
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/payments/lex-a' => FakePluginHttp::response(['openAmount' => 0, 'currency' => 'EUR', 'paymentStatus' => 'balanced', 'voucherType' => 'salesinvoice', 'voucherStatus' => 'paid', 'paidDate' => '2026-08-09T00:00:00.000+02:00', 'paymentItems' => [['paymentItemType' => 'irrecoverableReceivable', 'postingDate' => '2026-08-09T00:00:00.000+02:00', 'amount' => 119.0, 'currency' => 'EUR']]]),
            'https://api.lexware.io/v1/payments/lex-b' => FakePluginHttp::response(['message' => 'not found'], 404),
            'https://api.lexware.io/v1/payments/lex-c' => FakePluginHttp::response(['openAmount' => 0, 'currency' => 'EUR', 'paymentStatus' => 'balanced', 'voucherType' => 'salesinvoice', 'voucherStatus' => 'paid', 'paidDate' => '2026-08-07T00:00:00.000+02:00', 'paymentItems' => [['paymentItemType' => 'manualPayment', 'postingDate' => '2026-08-07T00:00:00.000+02:00', 'amount' => 119.0, 'currency' => 'EUR']]]),
        ]);

        $this->assertSame(2, (new LexofficeVoucherSync($this->organization->id))->enrichPaidDates($this->organization->id));
        $paid = LexofficeVoucher::query()->pluck('paid_date', 'external_id')->map(fn ($date): ?string => $date?->format('Y-m-d'))->all();
        $this->assertSame(['lex-a' => '2026-08-09', 'lex-b' => null, 'lex-c' => '2026-08-07'], $paid);
    }
}
