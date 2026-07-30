<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityLockdownTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Release;

use App\Enums\Security\IntegrityCheckStatus;
use App\Models\{AuditLog, IntegrityCheck, User};
use App\Services\Release\{IntegrityComparison, IntegrityLockdownService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MVP-448 — Lockdown-Option: löst NUR bei bewusst gesetztem Env-Flag,
 * signierter Release-Baseline und mindestens zwei konsekutiven
 * Abweichungsläufen aus. Alles andere (lokale Baseline, Signaturbruch,
 * transiente Einzelabweichung, Autoloader-Rauschen) darf keinen Ausfall
 * verursachen.
 */
class IntegrityLockdownTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('integrity.lockdown.mode', IntegrityLockdownService::MODE_CONFIRMED);
    }

    private function service(): IntegrityLockdownService {
        return app(IntegrityLockdownService::class);
    }

    /** @return array<string, mixed> */
    private function releaseManifest(): array {
        return ['source' => 'release', 'root' => 'root-hash', 'files' => [], 'packages' => []];
    }

    /**
     * Prüfläufe entstehen zeitlich aufsteigend — die Testdaten müssen das
     * spiegeln, sonst prüft der Streak-Test die falsche Reihenfolge.
     */
    private int $minuteOffset = 60;

    private function deviation(string $findingsHash = 'f1'): IntegrityCheck {
        return IntegrityCheck::query()->create([
            'ran_at' => now()->subMinutes($this->minuteOffset--),
            'status' => IntegrityCheckStatus::Deviation,
            'baseline_source' => 'release',
            'baseline_root' => 'root-hash',
            'files_checked' => 3,
            'modified_count' => 1,
            'findings_hash' => $findingsHash,
            'triggered_by' => 'cli',
        ]);
    }

    private function ok(): IntegrityCheck {
        return IntegrityCheck::query()->create([
            'ran_at' => now()->subMinutes($this->minuteOffset--),
            'status' => IntegrityCheckStatus::Ok,
            'baseline_source' => 'release',
            'baseline_root' => 'root-hash',
            'files_checked' => 3,
            'triggered_by' => 'cli',
        ]);
    }

    public function test_single_deviation_does_not_qualify(): void {
        $comparison = new IntegrityComparison(modified: ['app/x.php']);

        $this->assertFalse($this->service()->qualifies($this->deviation(), $this->releaseManifest(), $comparison));
    }

    public function test_two_consecutive_deviations_on_signed_release_qualify(): void {
        $comparison = new IntegrityComparison(modified: ['app/x.php']);
        $this->deviation('f1');
        $second = $this->deviation('f1');

        $this->assertSame(2, $this->service()->consecutiveDeviations($second));
        $this->assertTrue($this->service()->qualifies($second, $this->releaseManifest(), $comparison));
    }

    public function test_ok_run_in_between_resets_the_streak(): void {
        $this->deviation('f1');
        $this->ok();
        $latest = $this->deviation('f1');

        $this->assertSame(1, $this->service()->consecutiveDeviations($latest));
        $this->assertFalse($this->service()->qualifies($latest, $this->releaseManifest(), new IntegrityComparison(modified: ['app/x.php'])));
    }

    public function test_local_baseline_and_broken_signature_chain_never_qualify(): void {
        $this->deviation('f1');
        $check = $this->deviation('f1');
        $comparison = new IntegrityComparison(modified: ['app/x.php']);

        // Lokaler Freeze: kein Herkunftsbeweis.
        $this->assertFalse($this->service()->qualifies($check, ['source' => 'local', 'root' => 'r'], $comparison));

        // Signaturkette defekt: könnte selbst der Grund für den Diff sein.
        $withChain = new IntegrityComparison(modified: ['app/x.php'], chain: ['Signatur ungültig']);
        $this->assertFalse($this->service()->qualifies($check, $this->releaseManifest(), $withChain));
    }

    public function test_autoloader_only_package_noise_does_not_qualify(): void {
        $this->deviation('f1');
        $check = $this->deviation('f1');

        $autoloaderOnly = new IntegrityComparison(packages: ['composer-autoloader']);
        $this->assertFalse($this->service()->qualifies($check, $this->releaseManifest(), $autoloaderOnly));

        $realPackage = new IntegrityComparison(packages: ['acme/pkg']);
        $this->assertTrue($this->service()->qualifies($check, $this->releaseManifest(), $realPackage));
    }

    public function test_disarmed_mode_never_engages(): void {
        config()->set('integrity.lockdown.mode', IntegrityLockdownService::MODE_OFF);
        $this->deviation('f1');
        $check = $this->deviation('f1');

        $this->assertFalse($this->service()->armed());
        $this->assertFalse($this->service()->evaluate($check, $this->releaseManifest(), new IntegrityComparison(modified: ['app/x.php'])));
        $this->assertFalse($this->service()->isDown());
    }

    public function test_engaging_writes_audit_entry_and_raises_crisis(): void {
        User::factory()->create(['is_platform_admin' => true]);
        $this->deviation('f1');
        $check = $this->deviation('f1');

        // Wartungsmodus-Naht gefaked: ein echtes `artisan down` schreibt in
        // storage/framework und würde parallele Testprozesse mitsperren.
        $service = new class extends IntegrityLockdownService {
            public bool $down = false;

            protected function engageMaintenance(): void {
                $this->down = true;
            }

            protected function liftMaintenance(): void {
                $this->down = false;
            }

            public function isDown(): bool {
                return $this->down;
            }
        };

        $engaged = $service->evaluate($check, $this->releaseManifest(), new IntegrityComparison(modified: ['app/x.php']));

        $this->assertTrue($engaged);
        $this->assertTrue($service->isDown());
        $this->assertDatabaseHas('audit_logs', ['event' => 'integrity.lockdown_engaged']);
        $this->assertDatabaseHas('crisis_cases', ['category' => 'security', 'trigger_source' => 'integrity']);

        // Zweiter Lauf bei bereits aktiver Sperre alarmiert nicht erneut.
        $this->assertFalse($service->evaluate($check, $this->releaseManifest(), new IntegrityComparison(modified: ['app/x.php'])));
        $this->assertSame(1, AuditLog::query()->where('event', 'integrity.lockdown_engaged')->count());

        // Entsperren ist auditiert und idempotent.
        $this->assertTrue($service->release(null, 'test'));
        $this->assertFalse($service->release(null, 'test'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'integrity.lockdown_released']);
    }
}
