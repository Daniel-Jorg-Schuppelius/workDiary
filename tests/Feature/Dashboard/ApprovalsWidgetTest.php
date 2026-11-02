<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalsWidgetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Dashboard\Widgets\ApprovalsWidget;
use App\Enums\User\UserRole;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\Vacation;
use App\Models\Platform\User;
use App\Models\Travel\Expense;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kachel „Offene Genehmigungen“ (Vollscan 2026-11-02, gap-08): der Zähler
 * rechnet wie der Arbeitsvorrat — nach Zuständigkeit statt Admin-Rolle, eigene
 * Anträge nie.
 */
final class ApprovalsWidgetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 10:00:00');
        $this->setUpOrganization();
        // Der Arbeitsvorrat hält sein Ergebnis 60 s je Nutzer im Cache.
        Cache::flush();
    }

    private function vacation(User $user): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Pending->value,
        ]);
    }

    /** @return array{expenses:int, vacations:int}|null */
    private function pending(User $viewer): ?array {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        return app(DashboardService::class)->approverPending($viewer);
    }

    public function test_team_lead_without_admin_role_counts_vacations_of_others_but_never_own(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value, attributes: ['name' => 'Lea Leitung']);
        $employee = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Bernd Mitarbeiter']);
        $this->vacation($employee);
        $this->vacation($lead);
        // Spesen entscheidet nur die Admin-Rolle — für die Teamleitung zählt die Quelle 0.
        Expense::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $employee->id, 'status' => 'pending']);

        $this->assertSame(['expenses' => 0, 'vacations' => 1], $this->pending($lead));
        $this->assertTrue(app(ApprovalsWidget::class)->availableFor($lead), 'Die Kachel steht Genehmigenden ohne Admin-Rolle offen.');
    }

    public function test_admin_does_not_count_own_requests(): void {
        $admin = $this->orgAdmin(['name' => 'Anna Admin']);
        $employee = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Carla Kollegin']);
        $this->vacation($admin);
        Expense::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $admin->id, 'status' => 'pending']);
        Expense::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $employee->id, 'status' => 'pending']);
        Expense::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $employee->id, 'status' => 'draft']);

        $this->assertSame(['expenses' => 1, 'vacations' => 0], $this->pending($admin));
    }

    public function test_without_any_approval_responsibility_the_counter_is_null_and_the_tile_hidden(): void {
        $worker = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Dora Dienst']);
        $colleague = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Emil Einsatz']);
        $this->vacation($colleague);

        $this->assertNull($this->pending($worker));
        $this->assertFalse(app(ApprovalsWidget::class)->availableFor($worker));
    }
}
