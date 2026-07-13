<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EscalationLadderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\OpenIssue\OpenIssueStatus;
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{OpenIssue, Organization, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Eskalationsleiter Stufe 2/3 (MVP-331, Bauturbo A11): jede Stufe feuert
 * erst nach ihrer eigenen Frist (gemessen am Versand der vorherigen Stufe),
 * genau einmal (Dedup über das notification_dispatch_log) und an ihre eigene
 * Empfängergruppe; Bestandsregeln ohne Stufen bleiben einstufig.
 */
class EscalationLadderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $assignee;

    private User $teamlead;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->assignee = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeLadderRule(array $overrides = []): NotificationRule {
        // Determinismus: nur In-App, damit Mail-Rendering die Tests nicht berührt.
        return NotificationRule::factory()
            ->forEvent(NotificationEvent::OpenIssueOverdue)
            ->create($overrides + [
                'organization_id' => $this->organization->id,
                'channels' => [NotificationChannel::InApp->value],
                'escalation_enabled' => true,
                'escalate_after_hours' => 2,
                'escalation_role' => 'teamleitung',
            ]);
    }

    private function makeOverdueIssue(): OpenIssue {
        return OpenIssue::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->assignee->id,
            'assignee_user_id' => $this->assignee->id,
            'status' => OpenIssueStatus::Open->value,
            'due_at' => now()->subDay(),
        ]);
    }

    /** Alle Dedup-Log-Einträge künstlich altern lassen (Frist überschreiten). */
    private function ageDispatchLog(int $hours = 3): void {
        NotificationDispatchLog::query()->withoutGlobalScopes()
            ->update(['created_at' => now()->subHours($hours)]);
    }

    public function test_ladder_fires_level2_and_level3_each_after_own_deadline_exactly_once(): void {
        $fixed = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->makeLadderRule([
            'escalation2_after_hours' => 2,
            'escalation2_roles' => ['admin'],
            'escalation3_after_hours' => 2,
            'escalation3_user_ids' => [$fixed->id],
        ]);
        $this->makeOverdueIssue();

        // Lauf 1: nur die Initial-Benachrichtigung an die betroffene Person.
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $this->assignee->notifications()->count());
        $this->assertSame(0, $this->teamlead->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());

        // Stufe-1-Frist überschreiten → Stufe 1 an die Eskalationsrolle.
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $this->teamlead->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());

        // Sofortiger Folgelauf: Stufe-2-Frist (ab Stufe-1-Versand) läuft noch.
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, $this->admin->notifications()->count());

        // Stufe-2-Frist überschreiten → Stufe 2 an ihre Gruppe, Stufe 1 NICHT erneut.
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(1, $this->teamlead->notifications()->count());
        $this->assertSame(1, $this->assignee->notifications()->count());
        $data = (array) $this->admin->notifications()->first()?->data;
        $this->assertSame(NotificationDispatchLog::STAGE_ESCALATION2, $data['stage'] ?? null);

        // Stufe-3-Frist überschreiten → Stufe 3 an den festen Empfänger.
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $fixed->notifications()->count());
        $data3 = (array) $fixed->notifications()->first()?->data;
        $this->assertSame(NotificationDispatchLog::STAGE_ESCALATION3, $data3['stage'] ?? null);

        // Weiterer Lauf nach Fristablauf: alles bleibt bei genau einer Sendung (Dedup).
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $this->assignee->notifications()->count());
        $this->assertSame(1, $this->teamlead->notifications()->count());
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(1, $fixed->notifications()->count());
    }

    public function test_single_stage_legacy_rule_never_fires_higher_stages(): void {
        // Charakterisierung: Bestandsregel OHNE Stufen-Konfiguration bleibt einstufig.
        $this->makeLadderRule();
        $this->makeOverdueIssue();

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->assignee->notifications()->count());
        $this->assertSame(1, $this->teamlead->notifications()->count());

        $stages = NotificationDispatchLog::query()->withoutGlobalScopes()
            ->orderBy('id')->pluck('stage')->all();
        $this->assertSame([
            NotificationDispatchLog::STAGE_INITIAL,
            NotificationDispatchLog::STAGE_ESCALATION,
        ], $stages);
    }

    public function test_level2_without_recipients_is_not_configured_and_stays_silent(): void {
        // Frist ohne Empfängergruppe → Stufe gilt als nicht konfiguriert.
        $this->makeLadderRule(['escalation2_after_hours' => 2]);
        $this->makeOverdueIssue();

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, NotificationDispatchLog::query()->withoutGlobalScopes()
            ->where('stage', NotificationDispatchLog::STAGE_ESCALATION2)->count());
    }

    public function test_level2_recipients_are_organization_isolated(): void {
        $other = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $other->id]);

        $this->makeLadderRule([
            'escalation2_after_hours' => 2,
            'escalation2_roles' => ['admin'],
        ]);
        $this->makeOverdueIssue();

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->ageDispatchLog();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        // Stufe 2 erreicht nur den Admin der EIGENEN Organisation.
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(0, $foreignAdmin->notifications()->count());
    }
}
