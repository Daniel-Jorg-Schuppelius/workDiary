<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaQuotaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sla;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\{Customer, DiaryEntry, Project, SlaContract, SlaContractQuota, TimeEntry, User};
use App\Models\Notification\NotificationRule;
use App\Services\ServiceTicket\SlaQuotaService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 010 → Rang 44: SLA-Inklusivzeit-Kontingente. Prüft die Verbrauchs-
 * berechnung (Quellen Projekt/Auftrag, Periodengrenzen, nur billable) und die
 * pro Periode deduplizierte Warnung über den Fristen-Scanner.
 */
final class SlaQuotaTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $worker;
    private User $teamlead;
    private Customer $customer;
    private Project $project;
    private DiaryEntry $order;
    private SlaContract $contract;
    private SlaContractQuota $quota;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();

        $this->worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $this->order = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->contract = SlaContract::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'is_active' => true,
            'is_default' => false,
        ]);
        $this->quota = SlaContractQuota::query()->create([
            'organization_id' => $this->organization->id,
            'sla_contract_id' => $this->contract->id,
            'period_kind' => 'month',
            'included_minutes' => 600,
            'warn_threshold_pct' => 80,
        ]);

        NotificationRule::factory()->forEvent(NotificationEvent::SlaQuotaWarning)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => false,
            'recipient_roles' => ['teamleitung'],
        ]);
    }

    private function billableEntry(int $minutes, string $date, ?int $projectId = null, ?int $diaryId = null, bool $billable = true): void {
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'project_id' => $projectId,
            'diary_entry_id' => $diaryId,
            // Ohne Projekt darf activity_type nicht 'project' sein (Model-Guard).
            'activity_type' => $projectId !== null ? 'project' : 'admin',
            'date' => $date,
            'minutes' => $minutes,
            'billable' => $billable,
        ]);
    }

    public function test_consumption_counts_project_and_order_billable_time_within_period(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherProject = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $otherCustomer->id]);

        $this->billableEntry(300, '2026-06-10', projectId: $this->project->id);           // Projekt-verknüpft, in Periode
        $this->billableEntry(200, '2026-06-12', diaryId: $this->order->id);               // Auftrag-verknüpft, in Periode
        $this->billableEntry(100, '2026-06-14', projectId: $this->project->id, billable: false); // nicht abrechenbar → raus
        $this->billableEntry(999, '2026-05-31', projectId: $this->project->id);           // Vormonat → raus
        $this->billableEntry(500, '2026-06-10', projectId: $otherProject->id);            // anderer Kunde → raus

        $usage = app(SlaQuotaService::class)->usage($this->contract, $this->quota, Carbon::parse('2026-06-15'));

        $this->assertSame(500, $usage['consumed_minutes']); // 300 + 200
        $this->assertSame(600, $usage['included_minutes']);
        $this->assertSame(100, $usage['remaining_minutes']);
        $this->assertSame(83, $usage['percentage']);        // floor(500*100/600)
        $this->assertTrue($usage['threshold_reached']);     // 83 % >= 80 %
        $this->assertSame('2026-06', $usage['period_key']);
    }

    public function test_below_threshold_is_not_flagged(): void {
        $this->billableEntry(300, '2026-06-10', projectId: $this->project->id); // 50 %

        $usage = app(SlaQuotaService::class)->usage($this->contract, $this->quota, Carbon::parse('2026-06-15'));

        $this->assertSame(300, $usage['consumed_minutes']);
        $this->assertFalse($usage['threshold_reached']);
    }

    public function test_scanner_warns_team_lead_once_per_period(): void {
        Carbon::setTestNow('2026-06-15 09:00:00');
        $this->billableEntry(300, '2026-06-10', projectId: $this->project->id);
        $this->billableEntry(200, '2026-06-12', projectId: $this->project->id);

        // Zwei Läufe in derselben Periode → genau eine Warnung (Dedup).
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->teamlead->notifications()->count());
        $data = (array) $this->teamlead->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::SlaQuotaWarning->value, $data['event'] ?? null);
        $this->assertSame('2026-06', $this->quota->refresh()->last_warned_period);

        Carbon::setTestNow();
    }
}
