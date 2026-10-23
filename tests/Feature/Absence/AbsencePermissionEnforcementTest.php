<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbsencePermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Absence;

use App\Enums\User\{Permission, UserRole};
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\Vacation;
use App\Models\Platform\User;
use App\Models\Time\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Urlaub, Gleitzeit und Stempeln prüfen ihre Rechte (lk-06): Antrag, Stempeln
 * und eigene Gleitzeit behält jedes interne Profil; Entscheiden und Stornieren
 * fremder Anträge gehen mit vacation.approve/cancel an die Teamleitung,
 * fremde Gleitzeitkonten mit flex.manage.
 */
class AbsencePermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_requesting_and_stamping_stay_with_every_internal_profile(): void {
        foreach ([UserRole::Geschaeftsfuehrung, UserRole::Personalverwaltung, UserRole::Teamleitung, UserRole::Buchhaltung, UserRole::User, UserRole::Aussendienst, UserRole::Callcenter] as $role) {
            $user = $this->userWithRole($role->value);
            $this->assertTrue(Gate::forUser($user)->allows('create', Vacation::class), $role->value);
            $this->assertTrue(Gate::forUser($user)->allows('create', Attendance::class), $role->value);
        }

        $support = $this->userWithRole(UserRole::Support->value);
        $this->assertFalse(Gate::forUser($support)->allows('create', Vacation::class));
        $this->assertFalse(Gate::forUser($support)->allows('create', Attendance::class));
        $this->actingAs($support)->post(route('attendance.clock-in'))->assertForbidden();
        $this->assertTrue(Gate::forUser($this->orgAdmin())->allows('create', Vacation::class));
    }

    public function test_deciding_and_cancelling_follow_vacation_rights(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $colleague = $this->userWithRole(UserRole::User->value);
        // Mit einer weiteren berechtigten Person (Admin) gilt die Selbstfreigabe-Sperre.
        $admin = $this->orgAdmin();
        $vacation = $this->pendingVacation($worker);

        $this->assertTrue(Gate::forUser($lead)->allows('decide', $vacation));
        $this->assertFalse(Gate::forUser($colleague)->allows('decide', $vacation));
        $this->assertFalse(Gate::forUser($lead)->allows('decide', $this->pendingVacation($lead)), 'keine Selbstfreigabe');
        $this->assertTrue(Gate::forUser($admin)->allows('decide', $vacation));

        $this->actingAs($colleague)->patch(route('vacations.approve', $vacation))->assertForbidden();
        $this->actingAs($lead)->patch(route('vacations.approve', $vacation))->assertRedirect();
        $this->assertSame(VacationStatus::Approved, $vacation->refresh()->status);

        $this->assertTrue(Gate::forUser($lead)->allows('cancel', $vacation));
        $this->assertTrue(Gate::forUser($worker)->allows('cancel', $vacation), 'Antragsteller');
        $this->assertFalse(Gate::forUser($colleague)->allows('cancel', $vacation));

        $this->actingAs($lead)
            ->get(route('duties.index', ['tab' => 'urlaub']))
            ->assertOk()
            ->assertSee($worker->name);
    }

    public function test_flex_pages_follow_flex_rights(): void {
        $worker = $this->userWithRole(UserRole::User->value);
        $callcenter = $this->userWithRole(UserRole::Callcenter->value);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);

        $this->actingAs($callcenter)->get(route('flex.index'))->assertOk();
        $this->actingAs($this->actorIn($this->organization, [Permission::TimeEntryViewOwn]))->get(route('flex.index'))->assertForbidden();

        $this->assertTrue($lead->canViewOthersFlex());
        $this->assertTrue($accounting->canViewOthersFlex());
        $this->assertFalse($worker->canViewOthersFlex());
        $this->assertTrue($this->orgAdmin()->canViewOthersFlex());
        $this->actingAs($worker)->get(route('flex.admin'))->assertForbidden();
        $this->actingAs($lead)->get(route('flex.admin'))->assertRedirect();
    }

    private function pendingVacation(User $owner): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $owner->id,
            'start_date' => '2030-06-01',
            'end_date' => '2030-06-05',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Pending->value,
        ]);
    }
}
