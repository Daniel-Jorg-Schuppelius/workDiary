<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedurePolicyMatrixTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\User\Permission as P;
use App\Models\{Organization, ProcedureRun, ProcedureTemplate, User};
use App\Policies\{ProcedureRunPolicy, ProcedureTemplatePolicy};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Prozeduren (Feature 020): Vorlagen mit getrennten view/create/update/publish-
 * Rechten, Läufe mit view/start/execute/abort — jede Objekt-Aktion hart
 * organisationsgebunden; abort zusätzlich für den STARTER des Laufs (mit
 * Start-Recht) ohne eigenes Abort-Recht.
 */
final class ProcedurePolicyMatrixTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
    }

    private function makeRun(?User $creator = null, ?int $orgId = null): ProcedureRun {
        $run = new ProcedureRun;
        $run->organization_id = $orgId ?? $this->organization->id;
        $run->created_by_user_id = $creator?->id;

        return $run;
    }

    public function test_template_rights_are_separated_and_org_bound(): void {
        $policy = new ProcedureTemplatePolicy;
        $template = new ProcedureTemplate;
        $template->organization_id = $this->organization->id;

        $viewer = $this->actorIn($this->organization, [P::ProcedureTemplateView]);
        $this->assertTrue($policy->viewAny($viewer));
        $this->assertTrue($policy->view($viewer, $template));
        $this->assertFalse($policy->create($viewer));
        $this->assertFalse($policy->update($viewer, $template));
        $this->assertFalse($policy->publish($viewer, $template));

        $editor = $this->actorIn($this->organization, [P::ProcedureTemplateCreate, P::ProcedureTemplateUpdate]);
        $this->assertTrue($policy->create($editor));
        $this->assertTrue($policy->update($editor, $template));
        $this->assertFalse($policy->publish($editor, $template), 'Veröffentlichen ist separates Recht.');

        $publisher = $this->actorIn($this->organization, [P::ProcedureTemplatePublish]);
        $this->assertTrue($policy->publish($publisher, $template));

        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::ProcedureTemplateView, P::ProcedureTemplateUpdate, P::ProcedureTemplatePublish]);
        $this->actAsTeam($foreignOrg);
        $this->assertFalse($policy->view($attacker, $template));
        $this->assertFalse($policy->update($attacker, $template));
        $this->assertFalse($policy->publish($attacker, $template));
    }

    public function test_run_execution_rights_and_creator_abort(): void {
        $policy = new ProcedureRunPolicy;

        $starter = $this->actorIn($this->organization, [P::ProcedureRunView, P::ProcedureRunStart]);
        $executor = $this->actorIn($this->organization, [P::ProcedureRunExecute]);
        $aborter = $this->actorIn($this->organization, [P::ProcedureRunAbort]);
        $run = $this->makeRun($starter);

        $this->assertTrue($policy->viewAny($starter));
        $this->assertTrue($policy->view($starter, $run));
        $this->assertTrue($policy->start($starter));
        $this->assertTrue($policy->execute($executor, $run));
        $this->assertFalse($policy->execute($starter, $run), 'Ausführen verlangt execute-Recht.');

        // abort: eigenes Abort-Recht ODER Starter des Laufs mit Start-Recht.
        $this->assertTrue($policy->abort($aborter, $run));
        $this->assertTrue($policy->abort($starter, $run), 'Der Starter darf seinen eigenen Lauf abbrechen.');
        $other = $this->actorIn($this->organization, [P::ProcedureRunStart]);
        $this->assertFalse($policy->abort($other, $run), 'Fremde Läufe bricht man nicht ohne Abort-Recht ab.');
    }

    public function test_run_actions_end_at_org_boundary(): void {
        $policy = new ProcedureRunPolicy;
        $starter = $this->actorIn($this->organization, [P::ProcedureRunStart]);
        $run = $this->makeRun($starter);

        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::ProcedureRunView, P::ProcedureRunExecute, P::ProcedureRunAbort]);
        $this->actAsTeam($foreignOrg);

        $this->assertFalse($policy->view($attacker, $run));
        $this->assertFalse($policy->execute($attacker, $run));
        $this->assertFalse($policy->abort($attacker, $run));
    }
}
