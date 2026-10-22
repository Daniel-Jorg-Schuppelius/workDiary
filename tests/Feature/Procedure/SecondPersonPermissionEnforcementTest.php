<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecondPersonPermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\User\{Permission, UserRole};
use App\Models\Procedure\ProcedureRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Als zweite Person gegenzeichnen verlangt Übernahme- und Zeichnungsrecht
 * (procedure.secondPerson.take/sign) zusätzlich zum Ausführungsrecht (lk-06).
 */
class SecondPersonPermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_signing_as_second_person_needs_take_and_sign(): void {
        $admin = $this->orgAdmin();
        $run = ProcedureRun::factory()->create(['organization_id' => $this->organization->id, 'created_by_user_id' => $admin->id]);
        $this->actAsTeam($this->organization);

        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::User->value))->allows('signSecondPerson', $run));
        $this->assertTrue(Gate::forUser($this->userWithRole(UserRole::Teamleitung->value))->allows('signSecondPerson', $run));
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::Buchhaltung->value))->allows('signSecondPerson', $run));
        $signOnly = $this->actorIn($this->organization, [Permission::ProcedureRunExecute, Permission::ProcedureSecondPersonSign]);
        $this->assertFalse(Gate::forUser($signOnly)->allows('signSecondPerson', $run));
        $this->assertTrue(Gate::forUser($admin)->allows('signSecondPerson', $run));
    }
}
