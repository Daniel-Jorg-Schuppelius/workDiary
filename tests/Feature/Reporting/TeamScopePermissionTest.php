<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamScopePermissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\Sickness\SickLeaveKind;
use App\Enums\User\Permission;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Hr\Qualification;
use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Teamsicht der Personal-Auswertungen hängt am Recht der Fachliste, nicht
 * an der Admin-Rolle (MVP-1091, Entscheidung E1).
 */
class TeamScopePermissionTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array<string, array{string, Permission}> */
    public static function scopedReports(): array {
        return [
            'Anwesenheit' => ['reports.attendance', Permission::AttendanceViewAny],
            'Urlaub & Flex' => ['reports.absences', Permission::VacationViewAny],
            'Krankheiten' => ['reports.sickness', Permission::SickLeaveViewAny],
        ];
    }

    #[DataProvider('scopedReports')]
    public function test_team_scope_follows_the_domain_permission(string $route, Permission $permission): void {
        $plain = $this->orgUser();
        $this->actingAs($plain)->get(route($route, ['scope' => 'team']))
            ->assertOk()->assertViewHas('scope', 'mine')->assertViewHas('seesTeam', false);

        $holder = $this->orgUser();
        $this->grantPermissions($holder, [$permission]);
        $this->actingAs($holder)->get(route($route, ['scope' => 'team']))
            ->assertOk()->assertViewHas('scope', 'team')->assertViewHas('seesTeam', true);

        $this->actingAs($this->orgAdmin())->get(route($route, ['scope' => 'team']))
            ->assertOk()->assertViewHas('scope', 'team');
    }

    /** Krankheitstage anderer gehören zur Krankmeldungs-Sicht, nicht zur Urlaubssicht. */
    public function test_vacation_team_view_hides_sick_days_without_sick_leave_view(): void {
        $vacationOnly = $this->orgUser();
        $this->grantPermissions($vacationOnly, [Permission::VacationViewAny]);
        $this->actingAs($vacationOnly)->get(route('reports.absences', ['scope' => 'team']))
            ->assertOk()
            ->assertViewHas('showsSick', false)
            ->assertViewHas('typeBands', fn (array $bands): bool => ! in_array('sick', array_column($bands, 'key'), true));
        $csv = (string) $this->actingAs($vacationOnly)->get(route('reports.absences', ['scope' => 'team', 'export' => 'csv']))->getContent();
        $this->assertStringNotContainsString('Krank', $csv);

        $both = $this->orgUser();
        $this->grantPermissions($both, [Permission::VacationViewAny, Permission::SickLeaveViewAny]);
        $this->actingAs($both)->get(route('reports.absences', ['scope' => 'team']))->assertOk()->assertViewHas('showsSick', true);
    }

    public function test_qualification_matrix_shows_colleagues_with_qualification_manage(): void {
        $colleague = $this->orgUser(['name' => 'Kai Kollege']);
        $colleague->qualifications()->attach(Qualification::factory()->create(['organization_id' => $this->organization->id])->id);
        $names = static fn ($response): array => $response->viewData('users')->pluck('name')->all();

        $plain = $this->actingAs($this->orgUser())->get(route('reports.qualifications'))->assertOk()->assertViewMissing('filterUsers');
        $this->assertNotContains('Kai Kollege', $names($plain));

        $holder = $this->orgUser();
        $this->grantPermissions($holder, [Permission::QualificationManage]);
        $this->assertContains('Kai Kollege', $names($this->actingAs($holder)->get(route('reports.qualifications'))->assertOk()));
    }

    public function test_work_balance_of_others_needs_time_entry_view_any(): void {
        $colleague = Sqid::encode(User::class, (int) $this->orgUser()->id);

        $this->actingAs($this->orgUser())->get(route('reports.work-balance', ['user' => $colleague]))->assertForbidden();

        $holder = $this->orgUser();
        $this->grantPermissions($holder, [Permission::TimeEntryViewAny]);
        $this->actingAs($holder)->get(route('reports.work-balance', ['user' => $colleague]))
            ->assertOk()->assertViewHas('seesTeam', true);
    }

    public function test_absence_calendar_shows_reasons_only_with_sick_leave_view(): void {
        $colleague = $this->orgUser(['name' => 'Kai Kollege']);
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $colleague->id,
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-10',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);
        SickLeave::create([
            'organization_id' => $this->organization->id,
            'user_id' => $colleague->id,
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-06',
            'kind' => SickLeaveKind::cases()[0]->value,
        ]);
        SickLeave::create([
            'organization_id' => $this->organization->id,
            'user_id' => $colleague->id,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-08',
            'kind' => SickLeaveKind::cases()[0]->value,
            'cancelled_at' => now(),
        ]);
        $calendar = route('reports.absence-calendar', ['year' => 2026]);

        $this->actingAs($this->orgUser())->get($calendar)->assertOk()->assertDontSee('Kai Kollege');

        $vacationOnly = $this->orgUser();
        $this->grantPermissions($vacationOnly, [Permission::VacationViewAny]);
        $this->actingAs($vacationOnly)->get($calendar)
            ->assertOk()->assertSee('Kai Kollege')->assertViewHas('anonymize', true)->assertDontSee(__('Krank') . ': 2026-03-02');

        $withReasons = $this->orgUser();
        $this->grantPermissions($withReasons, [Permission::VacationViewAny, Permission::SickLeaveViewAny]);
        $this->actingAs($withReasons)->get($calendar)
            ->assertOk()->assertViewHas('anonymize', false)->assertSee(__('Krank') . ': 2026-03-02')
            ->assertDontSee(__('Krank') . ': 2026-09-07', false);
    }
}
