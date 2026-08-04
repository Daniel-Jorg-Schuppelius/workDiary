<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyLedgerImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Etsy;

use App\Models\{EtsyConnection, EtsyLedgerEntry, PluginSetting};
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Etsy\Services\EtsyLedgerImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-498 (Phase 66): Etsy-Ledger-Import — Pflicht-Zeitfenster
 * (min/max_created), Upsert je (Org, ledger_entry_id), Beträge ROH als
 * Integer (kein Money-Objekt, W0 §6), Payment→Receipt-Verknüpfung über den
 * Batch-Abruf, Checkpoint wandert auch bei leerem Fenster.
 */
final class EtsyLedgerImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private EtsyConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EtsyPlugin::ID,
            'enabled' => true,
            'settings' => ['keystring' => 'ks-1', 'shared_secret' => 'sec-1'],
        ]);

        $this->connection = EtsyConnection::create([
            'organization_id' => $this->organization->id,
            'shop_id' => 77,
            'etsy_user_id' => 12345,
            'access_token' => '12345.tok',
            'status' => EtsyConnection::STATUS_ACTIVE,
            'webhook_token' => 'hook-123',
        ]);
    }

    public function test_import_upserts_entries_and_links_payments(): void {
        $fake = FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77/payment-account/ledger-entries?*' => FakePluginHttp::response([
                'count' => 2,
                'results' => [
                    [
                        'entry_id' => 1,
                        'ledger_type' => 'payment',
                        'amount' => 4990,
                        'balance' => 4990,
                        'currency' => 'EUR',
                        'description' => 'Payment',
                        'reference_type' => 'payment',
                        'reference_id' => '555',
                        'created_timestamp' => 1754200000,
                    ],
                    [
                        'entry_id' => 2,
                        'ledger_type' => 'fee',
                        'amount' => -320,
                        'balance' => 4670,
                        'currency' => 'EUR',
                        'description' => 'Transaction fee',
                        'reference_type' => 'fee',
                        'reference_id' => 'f-1',
                        'created_timestamp' => 1754200100,
                    ],
                ],
            ]),
            'https://api.etsy.com/v3/application/shops/77/payments?*' => FakePluginHttp::response([
                'count' => 1,
                'results' => [['payment_id' => 555, 'receipt_id' => 900]],
            ]),
        ]);

        $result = app(EtsyLedgerImportService::class)->import($this->organization);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(1, $result['linked']);

        $payment = EtsyLedgerEntry::query()->where('ledger_entry_id', 1)->firstOrFail();
        $this->assertSame(4990, $payment->amount);
        $this->assertSame(900, (int) $payment->receipt_id);

        $fee = EtsyLedgerEntry::query()->where('ledger_entry_id', 2)->firstOrFail();
        $this->assertSame(-320, $fee->amount);
        $this->assertNull($fee->receipt_id);

        // Pflicht-Fenster: beide Parameter gehen mit.
        $fake->assertSent(fn(RequestInterface $r) => str_contains((string) $r->getUri(), 'min_created=')
            && str_contains((string) $r->getUri(), 'max_created='));

        $this->assertGreaterThan(0, $this->connection->refresh()->checkpoint('ledger_max_created'));
    }

    public function test_second_import_is_idempotent(): void {
        $stub = static fn(): \GuzzleHttp\Psr7\Response => FakePluginHttp::response([
            'count' => 1,
            'results' => [[
                'entry_id' => 1,
                'ledger_type' => 'fee',
                'amount' => -320,
                'balance' => -320,
                'currency' => 'EUR',
                'reference_type' => 'fee',
                'reference_id' => 'f-1',
                'created_timestamp' => 1754200100,
            ]],
        ]);

        FakePluginHttp::fake(['https://api.etsy.com/v3/application/shops/77/payment-account/ledger-entries?*' => $stub()]);
        app(EtsyLedgerImportService::class)->import($this->organization);

        FakePluginHttp::fake(['https://api.etsy.com/v3/application/shops/77/payment-account/ledger-entries?*' => $stub()]);
        $result = app(EtsyLedgerImportService::class)->import($this->organization);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, EtsyLedgerEntry::query()->count());
    }

    public function test_empty_window_still_advances_checkpoint(): void {
        FakePluginHttp::fake([
            'https://api.etsy.com/v3/application/shops/77/payment-account/ledger-entries?*' => FakePluginHttp::response(['count' => 0, 'results' => []]),
        ]);

        $result = app(EtsyLedgerImportService::class)->import($this->organization);

        $this->assertSame(0, $result['imported']);
        $this->assertGreaterThan(0, $this->connection->refresh()->checkpoint('ledger_max_created'));
    }

    public function test_without_active_connection_import_is_a_silent_noop(): void {
        $this->connection->forceFill(['status' => EtsyConnection::STATUS_DISCONNECTED])->save();
        $fake = FakePluginHttp::fake([]);

        $result = app(EtsyLedgerImportService::class)->import($this->organization);

        $this->assertSame(['imported' => 0, 'updated' => 0, 'linked' => 0], $result);
        $fake->assertNothingSent();
    }
}
