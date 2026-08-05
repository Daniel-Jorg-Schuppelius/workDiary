<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherSyncJobTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, ExternalReference, LexofficeVoucher, PluginSetting, Supplier, User};
use App\Plugins\Lexoffice\Jobs\{SyncOwnerVouchersJob, SyncVouchersJob};
use App\Plugins\Lexoffice\{LexofficePlugin, LexofficeVoucherSync};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Der globale Beleg-Sync (lexoffice.vouchers.sync) läuft NICHT mehr synchron im
 * Web-Request (Timeout-Gefahr bei vielen Kontakten), sondern dispatcht einen
 * Queue-Job im Hintergrund.
 */
final class LexofficeVoucherSyncJobTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);
    }

    public function test_enrich_paid_dates_fetches_payment_dates_for_paid_vouchers(): void {
        // Phase-54-Nachtrag: voucherlist liefert kein paidDate — die
        // Anreicherung holt es je bezahltem Beleg über den Payments-Endpunkt.
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/payments/ext-paid' => FakePluginHttp::response(['paidDate' => '2030-01-15T00:00:00.000+01:00', 'openAmount' => 0]),
            'https://api.lexoffice.io/v1/payments/ext-err' => FakePluginHttp::response(null, 404),
        ]);

        $mk = fn (string $ext, string $status, ?string $paidDate): LexofficeVoucher => LexofficeVoucher::create([
            'organization_id' => $this->organization->id,
            'external_id' => $ext,
            'voucher_type' => 'invoice',
            'voucher_status' => $status,
            'voucher_number' => strtoupper($ext),
            'voucher_date' => '2030-01-05',
            'paid_date' => $paidDate,
            'total_amount' => 100,
            'currency' => 'EUR',
        ]);

        $paid = $mk('ext-paid', 'paid', null);
        $err = $mk('ext-err', 'paid', null);
        $mk('ext-open', 'open', null);         // kein Kandidat (unbezahlt)
        $mk('ext-done', 'paid', '2030-01-01'); // schon angereichert

        $count = (new LexofficeVoucherSync('test-key'))->enrichPaidDates($this->organization->id);

        $this->assertSame(1, $count);
        $this->assertSame('2030-01-15', $paid->fresh()?->paid_date?->toDateString());
        // Fehler je Beleg werden toleriert (404 → Beleg bleibt ohne Datum).
        $this->assertNull($err->fresh()?->paid_date);
    }

    public function test_global_sync_dispatches_background_job(): void {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post(route('lexoffice.vouchers.sync'))
            ->assertRedirect()
            ->assertSessionHas('info');

        Queue::assertPushed(
            SyncVouchersJob::class,
            fn(SyncVouchersJob $job) => $job->organizationId === $this->organization->id,
        );
    }

    public function test_global_sync_errors_without_api_key(): void {
        Queue::fake();
        // Ohne konfigurierten API-Key → Fehlerpfad. Zeile entfernen statt roh
        // zu überschreiben (settings ist verschlüsselt; roher Write bricht den Cast).
        PluginSetting::query()->where('organization_id', $this->organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->delete();

        $this->actingAs($this->admin)
            ->post(route('lexoffice.vouchers.sync'))
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_dispatcher_fans_out_one_job_per_linked_owner(): void {
        Queue::fake();

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        foreach ([[$customer, 'lex-c'], [$supplier, 'lex-s']] as [$owner, $extId]) {
            ExternalReference::create([
                'organization_id' => $this->organization->id,
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                'referenceable_type' => $owner->getMorphClass(),
                'referenceable_id' => $owner->getKey(),
                'external_id' => $extId, 'synced_at' => now(),
            ]);
        }

        (new SyncVouchersJob($this->organization->id))->handle();

        Queue::assertPushed(SyncOwnerVouchersJob::class, 2);
        Queue::assertPushed(
            SyncOwnerVouchersJob::class,
            fn(SyncOwnerVouchersJob $job) => $job->kind === 'customer' && $job->ownerId === $customer->id,
        );
        Queue::assertPushed(
            SyncOwnerVouchersJob::class,
            fn(SyncOwnerVouchersJob $job) => $job->kind === 'supplier' && $job->ownerId === $supplier->id,
        );
    }
}
