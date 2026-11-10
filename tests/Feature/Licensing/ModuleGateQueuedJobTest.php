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
use App\Jobs\Contracts\ModuleJob;
use App\Jobs\Inventory\InventoryOutboxDeliveryJob;
use App\Models\Platform\{LicenseFlagOverride, Organization};
use App\Modules\Attributes\BelongsToModule;
use App\Services\Inventory\InventoryOutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * MVP-052 §5/Akzeptanz 5+10, zentral seit MVP-1518: ein bereits eingereihter,
 * modulgebundener Job erzeugt nach der Deaktivierung des Moduls keine neue
 * fachliche Wirkung — das Gate hängt als Bus-Pipe vor jedem Job.
 */
class ModuleGateQueuedJobTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        ModuleGateProbeJob::$runs = 0;
    }

    private function disable(Organization $org, string $flag): void {
        LicenseFlagOverride::query()->create(['organization_id' => $org->id, 'flag' => $flag, 'disabled_at' => now()]);
    }

    public function test_queued_job_does_nothing_when_module_disabled(): void {
        $org = Organization::factory()->enterprise()->create();
        $this->disable($org, 'module.lager');

        $entry = app(InventoryOutboxService::class)->enqueue($org->id, 'jtl', 'receipt', ['x' => 1], 'KEY-DISABLED');

        InventoryOutboxDeliveryJob::dispatchSync($entry->id);

        // Der Eintrag bleibt unverändert offen — keine Zustellung, kein Fehler.
        $this->assertSame(OutboxStatus::Pending, $entry->refresh()->status);
    }

    public function test_queued_job_processes_when_module_active(): void {
        $org = Organization::factory()->enterprise()->create();

        $entry = app(InventoryOutboxService::class)->enqueue($org->id, 'jtl', 'receipt', ['x' => 1], 'KEY-ACTIVE');

        // Modul aktiv → das Gate lässt den Job durch; ohne registrierten
        // Dispatcher endet er regulär als „failed" (nicht „pending").
        InventoryOutboxDeliveryJob::dispatchSync($entry->id);

        $this->assertNotSame(OutboxStatus::Pending, $entry->refresh()->status);
    }

    public function test_job_of_a_disabled_feature_module_ends_logged_without_effect(): void {
        $org = Organization::factory()->enterprise()->create();
        $this->disable($org, 'module.helpdesk');
        Log::spy();

        ModuleGateProbeJob::dispatch((int) $org->id);

        $this->assertSame(0, ModuleGateProbeJob::$runs);
        Log::shouldHaveReceived('info')->withArgs(static fn (string $message, array $context = []): bool => str_contains($message, 'Modul für die Organisation nicht aktiv')
            && $context === ['job' => ModuleGateProbeJob::class, 'module' => 'helpdesk', 'organization_id' => (int) $org->id])->once();
    }

    public function test_job_runs_for_an_active_module_and_without_organization(): void {
        $org = Organization::factory()->enterprise()->create();

        ModuleGateProbeJob::dispatch((int) $org->id);
        ModuleGateProbeJob::dispatch(null);

        $this->assertSame(2, ModuleGateProbeJob::$runs);
    }

    public function test_another_organization_keeps_its_active_module(): void {
        $off = Organization::factory()->enterprise()->create();
        $on = Organization::factory()->enterprise()->create();
        $this->disable($off, 'module.helpdesk');

        ModuleGateProbeJob::dispatch((int) $off->id);
        ModuleGateProbeJob::dispatch((int) $on->id);

        $this->assertSame(1, ModuleGateProbeJob::$runs);
    }
}

/** Fachmodul-Job ohne eigene Prüfung: nur das Gate der Queue entscheidet. */
#[BelongsToModule('helpdesk')]
final class ModuleGateProbeJob implements ModuleJob, ShouldQueue {
    use Dispatchable;
    use Queueable;

    public static int $runs = 0;

    public function __construct(private readonly ?int $organizationId) {}

    public function organizationId(): ?int {
        return $this->organizationId;
    }

    public function handle(): void {
        self::$runs++;
    }
}
