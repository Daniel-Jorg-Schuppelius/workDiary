<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityLockdownReleaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Release;

use App\Enums\Security\IntegrityCheckStatus;
use App\Models\Audit\AuditLog;
use App\Models\Platform\{IntegrityCheck, User};
use App\Services\Release\{CodeIntegrityService, IntegrityLockdownService};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File as FileFacade, Storage};
use Tests\TestCase;

/**
 * MVP-1612: Der Lockdown wird aufgehoben — automatisch mit einer neuen
 * Baseline, sonst durch den Plattformbetrieb über Befehl oder Knopf; ein
 * Wartungsmodus aus anderem Anlass bleibt stehen.
 */
final class IntegrityLockdownReleaseTest extends TestCase {
    use RefreshDatabase;

    private string $base = '';

    /** Wartungsmodus-Naht wie in IntegrityLockdownTest: kein echtes `artisan down`. */
    private IntegrityLockdownService $lockdown;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->base = sys_get_temp_dir() . '/wd_lockdown_' . uniqid();
        FileFacade::makeDirectory($this->base . '/src', 0775, true);
        file_put_contents($this->base . '/src/a.php', 'alpha');
        config()->set('integrity.base', $this->base);
        config()->set('integrity.paths', ['src']);
        config()->set('integrity.root_files', []);
        config()->set('integrity.vendor.enabled', false);

        $this->lockdown = new class extends IntegrityLockdownService {
            public bool $down = true;

            protected function liftMaintenance(): void {
                $this->down = false;
            }

            public function isDown(): bool {
                return $this->down;
            }
        };
        app()->instance(IntegrityLockdownService::class, $this->lockdown);
    }

    protected function tearDown(): void {
        FileFacade::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function engage(): void {
        $check = IntegrityCheck::query()->create([
            'ran_at' => now(),
            'status' => IntegrityCheckStatus::Deviation,
            'baseline_source' => 'release',
            'baseline_root' => 'root-hash',
            'files_checked' => 1,
            'triggered_by' => 'cli',
        ]);
        AuditLog::query()->create([
            'organization_id' => null,
            'user_id' => null,
            'event' => 'integrity.lockdown_engaged',
            'auditable_type' => MorphMap::stableKey(IntegrityCheck::class),
            'auditable_id' => $check->id,
            'changes' => ['root' => 'root-hash'],
        ]);
    }

    public function test_maintenance_mode_from_another_cause_is_not_lifted(): void {
        $this->assertFalse($this->lockdown->engaged());
        $this->assertFalse($this->lockdown->release(null, 'manual'));
        $this->assertTrue($this->lockdown->isDown());
    }

    public function test_new_baseline_lifts_the_lockdown(): void {
        $this->engage();

        app(CodeIntegrityService::class)->freeze('local');

        $this->assertFalse($this->lockdown->isDown());
        $released = AuditLog::query()->withoutGlobalScopes()->where('event', 'integrity.lockdown_released')->sole();
        $this->assertSame('baseline:local', $released->changes['reason']);
        $this->assertFalse($this->lockdown->engaged());
    }

    public function test_command_requires_a_reason_and_releases_with_audit(): void {
        $this->engage();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->artisan('integrity:release')->assertExitCode(1);
        $this->assertTrue($this->lockdown->isDown());

        $this->artisan('integrity:release', ['--reason' => 'Hotfix geprüft', '--user' => $admin->email])->assertExitCode(0);
        $this->assertFalse($this->lockdown->isDown());
        $released = AuditLog::query()->withoutGlobalScopes()->where('event', 'integrity.lockdown_released')->sole();
        $this->assertSame('manual: Hotfix geprüft', $released->changes['reason']);
        $this->assertSame($admin->id, $released->changes['by']);
    }

    public function test_platform_admin_releases_on_the_integrity_page(): void {
        $this->engage();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)->get(route('admin.integrity.index'))->assertOk()->assertSee(__('integrity.lockdown.release_action'));
        $this->actingAs($admin)->post(route('admin.integrity.release'))->assertRedirect(route('admin.integrity.index'));
        $this->assertFalse($this->lockdown->isDown());

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('admin.integrity.release'))->assertForbidden();
    }
}
