<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkflowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Enums\Whistleblowing\CaseStatus;
use App\Models\{Organization, User};
use App\Models\Whistleblowing\WhistleblowingCase;
use App\Services\Whistleblowing\{InvalidCaseTransition, ReporterCredentialService, WhistleblowingCaseWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WorkflowTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    private function workflow(): WhistleblowingCaseWorkflowService {
        return app(WhistleblowingCaseWorkflowService::class);
    }

    private function makeCase(): WhistleblowingCase {
        $org = Organization::factory()->create();
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = 'S';
        $case->description_ciphertext = 'D';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
        ]);
        $case->save();

        return $case;
    }

    private function actor(WhistleblowingCase $case): User {
        return User::factory()->create(['organization_id' => $case->organization_id]);
    }

    public function test_acknowledge_sets_status_and_timestamp_and_event(): void {
        $case = $this->makeCase();
        $this->workflow()->acknowledge($case, $this->actor($case));

        $case->refresh();
        $this->assertSame(CaseStatus::Acknowledged, $case->status);
        $this->assertNotNull($case->acknowledged_at);
        $this->assertDatabaseHas('whistleblowing_case_events', [
            'case_id' => $case->id, 'event' => 'case.acknowledged',
        ]);
    }

    public function test_invalid_transition_is_rejected(): void {
        $case = $this->makeCase(); // submitted

        $this->expectException(InvalidCaseTransition::class);
        $this->workflow()->transition($case, CaseStatus::ClosedSubstantiated, $this->actor($case), 'x');
    }

    public function test_close_requires_reason_and_sets_retention(): void {
        $case = $this->makeCase();
        $actor = $this->actor($case);
        $wf = $this->workflow();

        $wf->transition($case, CaseStatus::Triage, $actor);
        $wf->transition($case, CaseStatus::Investigating, $actor);

        // Abschluss ohne Begruendung → Fehler.
        try {
            $wf->transition($case, CaseStatus::ClosedSubstantiated, $actor, '  ');
            $this->fail('Abschluss ohne Begruendung haette scheitern muessen.');
        } catch (InvalidArgumentException) {
            // erwartet
        }

        $wf->transition($case, CaseStatus::ClosedSubstantiated, $actor, 'Verstoss bestaetigt.');
        $case->refresh();

        $this->assertSame(CaseStatus::ClosedSubstantiated, $case->status);
        $this->assertNotNull($case->closed_at);
        $this->assertNotNull($case->retention_due_at);

        // Begruendung liegt als interne, verschluesselte Notiz vor (nicht im Event).
        $this->assertSame(1, $case->messages()->where('visibility', 'internal')->count());
        $this->assertDatabaseHas('whistleblowing_case_events', [
            'case_id' => $case->id, 'event' => 'case.status_changed',
        ]);
    }
}
