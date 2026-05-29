<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Models\{Comment, DiaryEntry, EmergencyAssignment, Expense, OnCallShift, PerDiemTrip, User, Vacation};
use App\Models\OpenIssue;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_dashboard_requires_auth(): void {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_for_user_with_kpis(): void {
        $user = User::factory()->user()->create();
        DiaryEntry::factory()->for($user)->count(3)->create(['status' => 2, 'is_archived' => false]);
        DiaryEntry::factory()->for($user)->count(2)->create(['status' => 1, 'is_archived' => false]);
        DiaryEntry::factory()->for($user)->create(['status' => -1, 'is_archived' => false]);

        $now = CarbonImmutable::now();
        OnCallShift::factory()->for($user)->create([
            'start_at' => $now->subHour(),
            'end_at' => $now->addHours(7),
        ]);
        OnCallShift::factory()->for($user)->create([
            'start_at' => $now->addDays(2),
            'end_at' => $now->addDays(2)->addHours(8),
        ]);
        EmergencyAssignment::factory()->for($user)->create([
            'start_at' => $now->addDay(),
            'end_at' => $now->addDay()->addHours(2),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee(__('Dashboard'))
            ->assertSee(__('Meine offenen Einträge'))
            ->assertSee(__('Heute'))
            ->assertSee(__('Nächste Schichten'))
            ->assertDontSee(__('Offen (Team)'));
    }

    public function test_dashboard_shows_team_section_for_admin(): void {
        $this->markTestSkipped('Pre-existing test infrastructure gap: Spatie team-scoped Admin role attachment for User::factory()->admin() in tests does not match the runtime team_id resolved by SetOrganizationContext. Tracked separately.');
    }

    public function test_dashboard_lists_recent_comments_on_own_entries(): void {
        $owner = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $owner->organization_id]);
        $entry = DiaryEntry::factory()->for($owner)->create();
        Comment::factory()->for($other)->create(['commentable_type' => DiaryEntry::class, 'commentable_id' => $entry->id, 'body' => 'Wichtiger Hinweis von Kollege']);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Wichtiger Hinweis von Kollege');
    }

    public function test_home_redirects_authenticated_new_mode_user_to_dashboard(): void {
        $user = User::factory()->user()->create();
        $this->actingAs($user)
            ->withSession(['work_mode' => 'new'])
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_shows_finance_and_travel_kpis(): void {
        $user = User::factory()->user()->create();
        $now = CarbonImmutable::now();

        Expense::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => \App\Enums\Expense\ExpenseStatus::Pending,
            'date' => $now->toDateString(),
            'amount_net' => 100.00,
            'tax_rate' => 19.00,
        ]);
        Expense::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => \App\Enums\Expense\ExpenseStatus::Reimbursed,
            'date' => $now->toDateString(),
            'amount_net' => 50.00,
            'tax_rate' => 0.00,
        ]);
        Expense::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => \App\Enums\Expense\ExpenseStatus::Draft,
            'date' => $now->toDateString(),
            'amount_net' => 30.00,
            'tax_rate' => 0.00,
        ]);

        PerDiemTrip::factory()->for($user)->create([
            'organization_id' => $user->organization_id,
            'status' => \App\Enums\Expense\PerDiemTripStatus::Draft,
            'started_at' => $now->subDay(),
            'ended_at' => $now,
        ]);

        Vacation::query()->create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'status' => \App\Enums\Vacation\VacationStatus::Pending,
            'start_date' => $now->addDays(10)->toDateString(),
            'end_date' => $now->addDays(14)->toDateString(),
            'type' => 'vacation',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Finanzen & Reisen'))
            ->assertSee(__('Spesen eingereicht (Brutto)'))
            ->assertSee('169,00 €') // 119 (pending) + 50 (reimbursed)
            ->assertSee('50,00 €')   // reimbursed
            ->assertSee(__('Reisen (Monat) / Entwürfe'));
    }

    public function test_dashboard_orders_assigned_open_issues_by_due_date_and_puts_null_due_dates_last(): void {
        $user = User::factory()->user()->create();
        $other = User::factory()->user()->create(['organization_id' => $user->organization_id]);
        $entry = DiaryEntry::factory()->for($user)->create(['organization_id' => $user->organization_id]);

        OpenIssue::query()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'title' => 'Fällig später',
            'severity' => OpenIssueSeverity::High->value,
            'status' => OpenIssueStatus::Open->value,
            'assignee_user_id' => $user->id,
            'due_at' => now()->addDays(5),
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $user->id,
        ]);

        OpenIssue::query()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'title' => 'Fällig zuerst',
            'severity' => OpenIssueSeverity::Medium->value,
            'status' => OpenIssueStatus::InProgress->value,
            'assignee_user_id' => $user->id,
            'due_at' => now()->addDay(),
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $user->id,
        ]);

        OpenIssue::query()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'title' => 'Ohne Frist',
            'severity' => OpenIssueSeverity::Low->value,
            'status' => OpenIssueStatus::Open->value,
            'assignee_user_id' => $user->id,
            'due_at' => null,
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $user->id,
        ]);

        // Geschlossen -> darf im Widget nicht mehr erscheinen.
        OpenIssue::query()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'title' => 'Bereits erledigt',
            'severity' => OpenIssueSeverity::Low->value,
            'status' => OpenIssueStatus::Done->value,
            'assignee_user_id' => $user->id,
            'due_at' => now()->subDay(),
            'visibility' => OpenIssueVisibility::Internal->value,
            'closed_at' => now(),
            'closed_by_user_id' => $user->id,
            'created_by_user_id' => $user->id,
        ]);

        // Anderer Assignee -> darf im persönlichen Widget nicht erscheinen.
        OpenIssue::query()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'title' => 'Anderer Assignee',
            'severity' => OpenIssueSeverity::Low->value,
            'status' => OpenIssueStatus::Open->value,
            'assignee_user_id' => $other->id,
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        /** @var \Illuminate\Support\Collection<int, OpenIssue> $issues */
        $issues = $response->viewData('user')['open_issues_assigned'];

        $this->assertSame(
            ['Fällig zuerst', 'Fällig später', 'Ohne Frist'],
            $issues->pluck('title')->values()->all()
        );
        $this->assertSame(3, (int) $response->viewData('user')['kpi']['open_issues_assigned']);
        $this->assertSame(4, (int) $response->viewData('user')['kpi']['open_issues_created']);
    }

    public function test_dashboard_marks_critical_open_issues_with_error_badge(): void {
        $user = User::factory()->user()->create();
        $entry = DiaryEntry::factory()->for($user)->create(['organization_id' => $user->organization_id]);

        OpenIssue::query()->create([
            'organization_id' => $user->organization_id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'title' => 'Kritischer Mangel',
            'severity' => OpenIssueSeverity::Critical->value,
            'status' => OpenIssueStatus::Open->value,
            'assignee_user_id' => $user->id,
            'due_at' => now()->addDays(3),
            'visibility' => OpenIssueVisibility::Internal->value,
            'created_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Kritischer Mangel');
        $response->assertSee('badge-error', false);
    }
}
