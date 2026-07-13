<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskProblemScanTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{Problem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, MVP-156: Wirksamkeits-Scanner — überfällige Prüfung feuert
 * genau EINMAL (Dedup über das Dispatch-Log), geprüfte/geschlossene/
 * zukünftige Probleme feuern nicht, Eskalation an die Teamleitung nach
 * Schwellwert (Muster scanIsmsCorrectiveActions).
 */
final class HelpdeskProblemScanTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        NotificationRule::factory()->forEvent(NotificationEvent::ProblemEffectivenessDue)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function problem(array $overrides = []): Problem {
        return Problem::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Wiederkehrender Mailausfall',
            'owner_id' => $this->owner->id,
            'status' => 'resolved',
            'effectiveness_check_due_at' => Carbon::now()->subDay(),
            ...$overrides,
        ]);
    }

    public function test_overdue_effectiveness_check_notifies_owner_exactly_once(): void {
        $this->problem();

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->owner->notifications()->count(), 'Dedup über das Dispatch-Log.');
        $data = (array) $this->owner->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::ProblemEffectivenessDue->value, $data['event'] ?? null);
    }

    public function test_checked_closed_and_future_problems_do_not_fire(): void {
        // Bereits geprüft.
        $this->problem([
            'title' => 'Geprüft',
            'effectiveness_checked_at' => Carbon::now()->subHour(),
        ]);
        // Geschlossen (nicht resolved/known_error).
        $this->problem(['title' => 'Geschlossen', 'status' => 'closed']);
        // Frist liegt in der Zukunft.
        $this->problem(['title' => 'Zukunft', 'effectiveness_check_due_at' => Carbon::now()->addWeek()]);
        // Ohne Frist (nie gelöst).
        $this->problem(['title' => 'Ohne Frist', 'status' => 'open', 'effectiveness_check_due_at' => null]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, $this->owner->notifications()->count());
    }

    public function test_known_error_with_overdue_check_fires(): void {
        $this->problem(['title' => 'Known Error fällig', 'status' => 'known_error']);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->owner->notifications()->count());
    }

    public function test_escalates_to_team_lead_after_threshold(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        NotificationRule::query()
            ->where('organization_id', $this->organization->id)
            ->where('event', NotificationEvent::ProblemEffectivenessDue->value)
            ->update([
                'escalation_enabled' => true,
                'escalate_after_hours' => 2,
                'escalation_role' => 'teamleitung',
            ]);

        $this->problem();

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, $teamlead->notifications()->count());

        NotificationDispatchLog::query()->withoutGlobalScopes()
            ->where('event', NotificationEvent::ProblemEffectivenessDue->value)
            ->update(['created_at' => now()->subHours(3)]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(1, $teamlead->notifications()->count());
        $data = (array) $teamlead->notifications()->first()?->data;
        $this->assertSame('escalation', $data['stage'] ?? null);
    }
}
