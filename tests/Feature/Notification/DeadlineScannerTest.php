<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeadlineScannerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Models\{Asset, AssetAssignment, Document, MaintenancePlan, OpenIssue, Organization, User};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class DeadlineScannerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $assignee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->assignee = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        // Determinismus: nur In-App, damit Mail-Rendering den Test nicht berührt.
        foreach ([NotificationEvent::OpenIssueDueSoon, NotificationEvent::OpenIssueOverdue, NotificationEvent::DocumentExpiringSoon] as $event) {
            NotificationRule::factory()->forEvent($event)->create([
                'organization_id' => $this->organization->id,
                'channels' => [NotificationChannel::InApp->value],
            ]);
        }
    }

    private function makeIssue(array $attributes): OpenIssue {
        // $attributes zuerst — Array-Union behält die linke Seite (Overrides).
        return OpenIssue::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->assignee->id,
            'assignee_user_id' => $this->assignee->id,
            'status' => OpenIssueStatus::Open->value,
        ]);
    }

    public function test_overdue_issue_is_notified_exactly_once(): void {
        $this->makeIssue(['due_at' => now()->subDay()]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->assignee->notifications()->count());
        $data = (array) $this->assignee->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::OpenIssueOverdue->value, $data['event'] ?? null);

        $this->assertSame(1, NotificationDispatchLog::query()->withoutGlobalScopes()->count());
    }

    public function test_due_soon_issue_is_notified_exactly_once(): void {
        $this->makeIssue(['due_at' => now()->addDay()]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->assignee->notifications()->count());
        $data = (array) $this->assignee->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::OpenIssueDueSoon->value, $data['event'] ?? null);
    }

    public function test_closed_issue_does_not_notify(): void {
        $this->makeIssue(['due_at' => now()->subDay(), 'status' => OpenIssueStatus::Done->value]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, $this->assignee->notifications()->count());
    }

    public function test_escalation_fires_after_deadline_to_role_exactly_once(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        NotificationRule::query()
            ->where('organization_id', $this->organization->id)
            ->where('event', NotificationEvent::OpenIssueOverdue->value)
            ->update([
                'escalation_enabled' => true,
                'escalate_after_hours' => 2,
                'escalation_role' => 'teamleitung',
            ]);

        $this->makeIssue(['due_at' => now()->subDay()]);

        // Erst-Lauf: Initial-Benachrichtigung an die betroffene Person.
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, $teamlead->notifications()->count());

        // Frist überschreiten: Initial-Versand künstlich altern lassen.
        NotificationDispatchLog::query()->withoutGlobalScopes()
            ->update(['created_at' => now()->subHours(3)]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $teamlead->notifications()->count());
        $data = (array) $teamlead->notifications()->first()?->data;
        $this->assertSame('escalation', $data['stage'] ?? null);

        // Dritter Lauf: Eskalation wird nicht erneut versendet (Dedup).
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $teamlead->notifications()->count());

        // Betroffene Person bleibt bei genau einer Initial-Benachrichtigung.
        $this->assertSame(1, $this->assignee->notifications()->count());
    }

    public function test_expiring_document_notifies_creator_once(): void {
        Document::factory()->expiringInDays(10)->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->assignee->id,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->assignee->notifications()->count());
        $data = (array) $this->assignee->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::DocumentExpiringSoon->value, $data['event'] ?? null);
    }

    public function test_maintenance_due_soon_notifies_team_lead_once(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'next_due_on' => now()->addDays(5)->toDateString(),
        ]);

        $this->artisan('notifications:scan-deadlines', ['--expiring-days' => 30])->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines', ['--expiring-days' => 30])->assertExitCode(0);

        $this->assertSame(1, $teamlead->notifications()->count());
        $data = (array) $teamlead->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::MaintenanceDueSoon->value, $data['event'] ?? null);
    }

    public function test_maintenance_overdue_notifies_team_lead_exactly_once(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'next_due_on' => now()->subDays(3)->toDateString(),
        ]);

        // Zweiter Lauf darf nicht erneut feuern (Dedup pro Plan + Stufe).
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $teamlead->notifications()->count());
        $data = (array) $teamlead->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::MaintenanceOverdue->value, $data['event'] ?? null);
    }

    public function test_maintenance_overdue_notifies_current_asset_holder_as_responsible(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $holder = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        AssetAssignment::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'assigned_to_user_id' => $holder->id,
        ]);
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'is_active' => true,
            'next_due_on' => now()->subDays(3)->toDateString(),
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        // Verantwortlicher (aktueller Ausgabe-Inhaber) + Teamleitung als
        // Mitwisser — je genau einmal (Dedup).
        $this->assertSame(1, $holder->notifications()->count());
        $data = (array) $holder->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::MaintenanceOverdue->value, $data['event'] ?? null);
        $this->assertSame(1, $teamlead->notifications()->count());
    }

    public function test_completed_or_not_due_maintenance_plans_do_not_notify(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        // Deaktiviert (z. B. nach Durchführung/Stilllegung) trotz vergangenem Datum.
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => null,
            'is_active' => false,
            'next_due_on' => now()->subDays(3)->toDateString(),
        ]);
        // Noch nicht fällig (außerhalb des Vorlaufs von 30 Tagen).
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => null,
            'is_active' => true,
            'next_due_on' => now()->addDays(90)->toDateString(),
        ]);
        // Ohne Fälligkeitsdatum (Zähler-/Kilometerintervall ohne Prognose).
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => null,
            'is_active' => true,
            'next_due_on' => null,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, $teamlead->notifications()->count());
    }

    public function test_maintenance_overdue_escalation_ladder_reaches_level2(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $fixed = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        NotificationRule::factory()
            ->forEvent(NotificationEvent::MaintenanceOverdue)
            ->create([
                'organization_id' => $this->organization->id,
                'channels' => [NotificationChannel::InApp->value],
                'notify_affected' => false,
                'recipient_roles' => ['teamleitung'],
                'escalation_enabled' => true,
                'escalate_after_hours' => 2,
                'escalation_role' => 'admin',
                'escalation2_after_hours' => 2,
                'escalation2_user_ids' => [$fixed->id],
            ]);
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => null,
            'is_active' => true,
            'next_due_on' => now()->subDays(3)->toDateString(),
        ]);

        // Lauf 1: Initialstufe an die Teamleitung.
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $teamlead->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());

        // Stufe-1-Frist überschreiten → Eskalationsrolle (admin).
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $fixed->notifications()->count());

        // Sofortiger Folgelauf: Stufe-2-Frist (ab Stufe-1-Versand) läuft noch.
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, $fixed->notifications()->count());

        // Stufe-2-Frist überschreiten → fester Empfänger, keine Wiederholungen.
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $fixed->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $teamlead->notifications()->count());
        $data = (array) $fixed->notifications()->first()?->data;
        $this->assertSame(NotificationDispatchLog::STAGE_ESCALATION2, $data['stage'] ?? null);
    }

    public function test_maintenance_scan_is_organization_isolated(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $other = Organization::factory()->create();
        $foreignLead = User::factory()->teamleitung()->create(['organization_id' => $other->id]);

        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => null,
            'is_active' => true,
            'next_due_on' => now()->subDays(3)->toDateString(),
        ]);
        MaintenancePlan::factory()->create([
            'organization_id' => $other->id,
            'asset_id' => null,
            'is_active' => true,
            'next_due_on' => now()->subDays(3)->toDateString(),
        ]);

        // Realer Scanner-Kontext: der Konsolen-Prozess läuft ohne Mandanten-
        // Bindung und sieht damit die Pläne ALLER Organisationen.
        app()->forgetInstance('currentOrganization');

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        // Jede Teamleitung sieht ausschließlich die Fälligkeit der eigenen Organisation.
        $this->assertSame(1, $teamlead->notifications()->count());
        $this->assertSame(1, $foreignLead->notifications()->count());
    }

    /** Alle Dedup-Log-Einträge künstlich altern lassen (Frist überschreiten). */
    private function ageDispatchLog(int $hours = 3): void {
        NotificationDispatchLog::query()->withoutGlobalScopes()
            ->update(['created_at' => now()->subHours($hours)]);
    }

    public function test_maintenance_overdue_escalates_to_admin_after_window(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        NotificationRule::factory()
            ->forEvent(NotificationEvent::MaintenanceOverdue)
            ->create([
                'organization_id' => $this->organization->id,
                'notify_affected' => false,
                'recipient_roles' => ['teamleitung'],
                'escalation_enabled' => true,
                'escalate_after_hours' => 2,
                'escalation_role' => 'admin',
            ]);
        MaintenancePlan::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            'next_due_on' => now()->subDays(3)->toDateString(),
        ]);

        // Erst-Lauf: Initialstufe (Admin ist kein initialer Empfänger).
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, $admin->notifications()->count());

        // Eskalationsfenster überschreiten.
        NotificationDispatchLog::query()->withoutGlobalScopes()->update(['created_at' => now()->subHours(3)]);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $admin->notifications()->count());
        $data = (array) $admin->notifications()->first()?->data;
        $this->assertSame('escalation', $data['stage'] ?? null);
    }
}
