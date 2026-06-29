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

use App\Models\{Customer, ExternalReference, PluginSetting, Supplier, User};
use App\Plugins\Lexoffice\Jobs\{SyncOwnerVouchersJob, SyncVouchersJob};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
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
