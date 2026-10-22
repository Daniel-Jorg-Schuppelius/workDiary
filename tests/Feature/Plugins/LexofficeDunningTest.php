<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeDunningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\Platform\User;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\Services\LexofficeDunningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Lexoffice-Mahnung aus einer überfälligen Rechnung (Folgebeleg via
 * precedingSalesVoucherId).
 */
final class LexofficeDunningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->pluginSecret('lexoffice', ['base_url' => 'https://api.lexware.io/v1', 'api_key' => 'test-key']);
    }

    private function overdueInvoice(): LexofficeVoucher {
        return LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'inv-1',
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'overdue',
            'voucher_number' => 'RE-1', 'voucher_date' => '2026-05-01',
            'total_amount' => '119.00', 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    private function fakeInvoiceAndDunning(): FakePluginHttp {
        return FakePluginHttp::fake([
            // Antwort wie GET /invoices/{id} der API: Anschrift mit Land, Positionen mit Betrag und Rabatt.
            'https://api.lexware.io/v1/invoices/inv-1' => FakePluginHttp::response([
                'id' => 'inv-1',
                'voucherStatus' => 'overdue',
                'voucherNumber' => 'RE-1',
                'voucherDate' => '2026-05-01T00:00:00.000+02:00',
                'address' => ['contactId' => 'lex-contact-1', 'name' => 'Kunde AG', 'street' => 'Ring 3', 'zip' => '1010', 'city' => 'Wien', 'countryCode' => 'AT'],
                'lineItems' => [
                    [
                        'id' => 'art-1', 'type' => 'service', 'name' => 'Leistung', 'description' => 'Mai', 'quantity' => 2, 'unitName' => 'Stunde',
                        'unitPrice' => ['currency' => 'EUR', 'netAmount' => 50.0, 'grossAmount' => 59.5, 'taxRatePercentage' => 19],
                        'discountPercentage' => 10, 'lineItemAmount' => 90.0,
                    ],
                    ['type' => 'text', 'name' => 'Hinweis', 'description' => 'Bitte zahlen'],
                ],
                'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 90.0, 'totalGrossAmount' => 107.1, 'totalTaxAmount' => 17.1],
                'taxConditions' => ['taxType' => 'net', 'taxTypeNote' => null],
                'shippingConditions' => ['shippingDate' => '2026-05-01T00:00:00.000+02:00', 'shippingType' => 'service'],
            ], 200),
            'https://api.lexware.io/v1/dunnings*' => FakePluginHttp::response(['id' => 'dun-1'], 201),
        ]);
    }

    public function test_push_creates_dunning_from_overdue_invoice(): void {
        $voucher = $this->overdueInvoice();
        $fake = $this->fakeInvoiceAndDunning();

        $reference = app(LexofficeDunningService::class)->push($voucher);

        $this->assertSame(LexofficeDunningService::EXT_TYPE_DUNNING, $reference->external_type);
        $this->assertSame('dun-1', $reference->external_id);
        $this->assertSame($voucher->getMorphClass(), $reference->referenceable_type);

        // InvoicesEndpoint::get() + DunningsEndpoint::create(): ein GET, ein POST ohne finalize.
        $this->assertSame(
            ['GET https://api.lexware.io/v1/invoices/inv-1', 'POST https://api.lexware.io/v1/dunnings?precedingSalesVoucherId=inv-1'],
            array_map(static fn (array $entry): string => $entry['request']->getMethod() . ' ' . $entry['request']->getUri(), $fake->recorded()),
        );
        $body = json_decode((string) $fake->recorded()[1]['request']->getBody(), true);
        $this->assertEqualsCanonicalizing(['voucherDate', 'address', 'lineItems', 'totalPrice', 'taxConditions', 'title'], array_keys($body));
        $this->assertEquals(['contactId' => 'lex-contact-1', 'name' => 'Kunde AG', 'street' => 'Ring 3', 'zip' => '1010', 'city' => 'Wien', 'countryCode' => 'AT'], $body['address'], 'Anschrift der Rechnung unverändert, kein ergänztes Land.');
        $this->assertEquals([
            'id' => 'art-1', 'type' => 'service', 'name' => 'Leistung', 'description' => 'Mai', 'quantity' => 2, 'unitName' => 'Stunde',
            'unitPrice' => ['currency' => 'EUR', 'netAmount' => 50.0, 'grossAmount' => 59.5, 'taxRatePercentage' => 19],
            'discountPercentage' => 10, 'lineItemAmount' => 90.0,
        ], $body['lineItems'][0]);
        $this->assertEquals(['type' => 'text', 'name' => 'Hinweis', 'description' => 'Bitte zahlen'], $body['lineItems'][1]);
        $this->assertSame(['currency' => 'EUR'], $body['totalPrice']);
        $this->assertSame(['taxType' => 'net'], $body['taxConditions']);
        $this->assertSame(__('Mahnung'), $body['title']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $body['voucherDate']);
        $this->assertSame(['lexoffice_id' => 'dun-1', 'preceding_invoice_id' => 'inv-1', 'id' => 'dun-1'], $reference->payload);
    }

    public function test_push_reports_a_failed_dunning_with_status_and_body(): void {
        $voucher = $this->overdueInvoice();
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices/inv-1' => FakePluginHttp::response(['id' => 'inv-1', 'address' => ['contactId' => 'lex-contact-1', 'countryCode' => 'DE'], 'lineItems' => [], 'totalPrice' => ['currency' => 'EUR'], 'taxConditions' => ['taxType' => 'net']]),
            'https://api.lexware.io/v1/dunnings*' => FakePluginHttp::response(['message' => 'invoice not overdue'], 406),
        ]);

        try {
            app(LexofficeDunningService::class)->push($voucher);
            $this->fail('406 muss als RuntimeException durchschlagen.');
        } catch (RuntimeException $e) {
            $this->assertSame('Lexoffice dunning failed: HTTP 406 {"message":"invoice not overdue"}', $e->getMessage());
        }
        $this->assertNull(app(LexofficeDunningService::class)->reference($voucher));
    }

    public function test_push_rejects_non_invoice_voucher(): void {
        $voucher = LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'oc-1',
            'voucher_type' => 'orderconfirmation', 'voucher_status' => 'open',
            'voucher_number' => 'AB-1', 'currency' => 'EUR', 'archived' => false,
        ]);

        $this->expectException(RuntimeException::class);
        app(LexofficeDunningService::class)->push($voucher);
    }

    public function test_controller_creates_dunning(): void {
        $voucher = $this->overdueInvoice();
        $this->fakeInvoiceAndDunning();

        $this->actingAs($this->admin)
            ->post(route('lexoffice.vouchers.dunning', $voucher))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficeDunningService::EXT_TYPE_DUNNING,
            'external_id' => 'dun-1',
            'referenceable_id' => $voucher->id,
        ]);
    }
}
