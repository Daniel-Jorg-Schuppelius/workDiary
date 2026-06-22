<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleGateQueuedJobTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Enums\Inventory\OutboxStatus;
use App\Jobs\Integration\InventoryOutboxDeliveryJob;
use App\Models\{LicenseFlagOverride, Organization};
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryOutboxService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MVP-052 §5/Akzeptanz 5+10: ein bereits eingequeue-ter, modulgebundener Job
 * erzeugt nach der Deaktivierung des Moduls keine neue fachliche Wirkung.
 */
class ModuleGateQueuedJobTest extends TestCase {
    use RefreshDatabase;

    public function test_queued_job_does_nothing_when_module_disabled(): void {
        $org = Organization::factory()->enterprise()->create();
        LicenseFlagOverride::query()->create([
            'organization_id' => $org->id,
            'flag' => 'module.lager',
            'disabled_at' => now(),
        ]);

        $outbox = app(InventoryOutboxService::class);
        $entry = $outbox->enqueue($org->id, 'jtl', 'receipt', ['x' => 1], 'KEY-DISABLED');

        (new InventoryOutboxDeliveryJob($entry->id))->handle($outbox, app(ExternalInventoryDispatcherResolver::class));

        // Der Eintrag bleibt unverändert offen — keine Zustellung, kein Fehler.
        $this->assertSame(OutboxStatus::Pending, $entry->refresh()->status);
    }

    public function test_queued_job_processes_when_module_active(): void {
        $org = Organization::factory()->enterprise()->create();

        $outbox = app(InventoryOutboxService::class);
        $entry = $outbox->enqueue($org->id, 'jtl', 'receipt', ['x' => 1], 'KEY-ACTIVE');

        // Modul aktiv → das Gate lässt den Job durch; ohne registrierten
        // Dispatcher endet er regulär als „failed" (nicht „pending").
        (new InventoryOutboxDeliveryJob($entry->id))->handle($outbox, app(ExternalInventoryDispatcherResolver::class));

        $this->assertNotSame(OutboxStatus::Pending, $entry->refresh()->status);
    }
}
