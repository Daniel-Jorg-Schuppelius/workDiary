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

use App\Models\{LexofficeVoucher, User};
use App\Plugins\Lexoffice\{LexofficeDunningService, LexofficePlugin};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lexoffice-Mahnung aus einer überfälligen Rechnung (Folgebeleg via
 * precedingSalesVoucherId).
 */
final class LexofficeDunningTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');
    }

    private function overdueInvoice(): LexofficeVoucher {
        return LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'inv-1',
            'voucher_type' => 'salesinvoice', 'voucher_status' => 'overdue',
            'voucher_number' => 'RE-1', 'voucher_date' => '2026-05-01',
            'total_amount' => '119.00', 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    private function fakeInvoiceAndDunning(): void {
        Http::fake([
            'https://api.lexoffice.io/v1/invoices/inv-1' => Http::response([
                'id' => 'inv-1',
                'address' => ['contactId' => 'lex-contact-1'],
                'lineItems' => [[
                    'type' => 'custom', 'name' => 'Leistung', 'quantity' => 1, 'unitName' => 'Stück',
                    'unitPrice' => ['currency' => 'EUR', 'netAmount' => 100.0, 'taxRatePercentage' => 19.0],
                ]],
                'totalPrice' => ['currency' => 'EUR'],
                'taxConditions' => ['taxType' => 'net'],
            ], 200),
            'https://api.lexoffice.io/v1/dunnings*' => Http::response(['id' => 'dun-1'], 201),
        ]);
    }

    public function test_push_creates_dunning_from_overdue_invoice(): void {
        $voucher = $this->overdueInvoice();
        $this->fakeInvoiceAndDunning();

        $reference = app(LexofficeDunningService::class)->push($voucher);

        $this->assertSame(LexofficeDunningService::EXT_TYPE_DUNNING, $reference->external_type);
        $this->assertSame('dun-1', $reference->external_id);
        $this->assertSame($voucher->getMorphClass(), $reference->referenceable_type);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/dunnings')) {
                return false;
            }

            return str_contains($request->url(), 'precedingSalesVoucherId=inv-1')
                && str_contains((string) data_get($request->data(), 'lineItems.0.name'), 'Leistung')
                && data_get($request->data(), 'address.contactId') === 'lex-contact-1';
        });
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
