<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WaitStepTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\ProcedureStepRunStatus;
use App\Models\ProcedureStepRun;
use App\Services\Procedure\WaitStepService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Serverseitige Warte-/Trockenschritte (Feature 047, MVP-064): Blockade gegen
 * den persistierten Fortsetzungszeitpunkt (nicht über Reload/anderen Client
 * umgehbar); vorzeitige Fortsetzung nur als auditierte Abweichung.
 */
final class WaitStepTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private WaitStepService $wait;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->wait = app(WaitStepService::class);
    }

    public function test_wait_blocks_continuation_until_elapsed(): void {
        $step = ProcedureStepRun::factory()->create();
        $this->wait->beginWait($step, 3600);

        $this->assertFalse($this->wait->canContinue($step->fresh()));

        $this->expectException(RuntimeException::class);
        $this->wait->continueStep($step->fresh());
    }

    public function test_block_cannot_be_bypassed_by_reload(): void {
        $step = ProcedureStepRun::factory()->create();
        $this->wait->beginWait($step, 3600);

        // „Neuladen": frische Instanz aus der DB – die serverseitige Frist gilt weiter.
        $reloaded = ProcedureStepRun::query()->findOrFail($step->id);
        $this->assertFalse($this->wait->canContinue($reloaded));

        try {
            $this->wait->continueStep($reloaded);
            $this->fail('Vorzeitige Fortsetzung darf nicht möglich sein.');
        } catch (RuntimeException) {
            // erwartet
        }
    }

    public function test_early_continue_allowed_as_audited_deviation(): void {
        $step = ProcedureStepRun::factory()->create();
        $this->wait->beginWait($step, 3600);

        $this->wait->continueStep($step, asDeviation: true, reason: 'Dringend benötigt');

        $fresh = $step->fresh();
        $this->assertSame(ProcedureStepRunStatus::Deviated, $fresh->status);
        $this->assertSame('Dringend benötigt', $fresh->note);
    }

    public function test_continue_after_elapsed_marks_done(): void {
        $step = ProcedureStepRun::factory()->create();
        $this->wait->beginWait($step, 0); // wait_until = jetzt → sofort abgelaufen

        $this->assertTrue($this->wait->canContinue($step->fresh()));
        $this->wait->continueStep($step->fresh());

        $this->assertSame(ProcedureStepRunStatus::Done, $step->fresh()->status);
    }
}
