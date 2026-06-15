<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyEventTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Safety;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\Safety\{SafetyEventKind, SafetyEventSeverity, SafetyEventStatus};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{OpenIssue, SafetyEvent, User};
use App\Services\Safety\SafetyEventService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SafetyEventTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    private function lead(): User {
        return User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function field(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    public function test_lead_can_view_register(): void {
        $this->actingAs($this->lead())
            ->get(route('safety-events.index'))
            ->assertOk()
            ->assertViewIs('safety-events.index');
    }

    public function test_field_staff_can_report_event(): void {
        $field = $this->field();

        $this->actingAs($field)
            ->post(route('safety-events.store'), [
                'kind' => SafetyEventKind::Hazard->value,
                'severity' => SafetyEventSeverity::Low->value,
                'occurred_at' => now()->format('Y-m-d\TH:i'),
                'location' => 'Halle 2',
                'description' => 'Ölspur am Boden',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('safety_events', [
            'organization_id' => $this->organization->id,
            'kind' => SafetyEventKind::Hazard->value,
            'status' => SafetyEventStatus::Reported->value,
            'reported_by_user_id' => $field->id,
            'event_no' => 1,
        ]);
    }

    public function test_event_no_runs_per_organization(): void {
        $lead = $this->lead();
        $service = app(SafetyEventService::class);

        $first = $service->create($lead, ['kind' => SafetyEventKind::Defect->value, 'description' => 'A']);
        $second = $service->create($lead, ['kind' => SafetyEventKind::Defect->value, 'description' => 'B']);

        $this->assertSame(1, $first->event_no);
        $this->assertSame(2, $second->event_no);
    }

    public function test_close_requires_root_cause(): void {
        $lead = $this->lead();
        $service = app(SafetyEventService::class);
        $event = $service->create($lead, ['kind' => SafetyEventKind::Hazard->value, 'description' => 'X']);

        $this->expectException(ValidationException::class);
        $service->transition($event, SafetyEventStatus::Closed, $lead);
    }

    public function test_close_sets_closed_metadata_with_root_cause(): void {
        $lead = $this->lead();
        $service = app(SafetyEventService::class);
        $event = $service->create($lead, ['kind' => SafetyEventKind::Hazard->value, 'description' => 'X']);

        $service->update($event, ['root_cause' => 'Fehlende Absperrung']);
        $closed = $service->transition($event->refresh(), SafetyEventStatus::Closed, $lead);

        $this->assertSame(SafetyEventStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame($lead->id, $closed->closed_by_user_id);
    }

    public function test_invalid_transition_is_rejected(): void {
        $lead = $this->lead();
        $service = app(SafetyEventService::class);
        $event = $service->create($lead, ['kind' => SafetyEventKind::Hazard->value, 'description' => 'X']);

        // reported → measuresDefined is not allowed (must investigate first).
        $this->expectException(ValidationException::class);
        $service->transition($event, SafetyEventStatus::MeasuresDefined, $lead);
    }

    public function test_critical_event_notifies_leadership(): void {
        NotificationRule::factory()->forEvent(NotificationEvent::SafetyCriticalEvent)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => false,
            'recipient_roles' => [\App\Enums\User\UserRole::Teamleitung->value, \App\Enums\User\UserRole::Admin->value],
        ]);
        $lead = $this->lead();
        $field = $this->field();

        app(SafetyEventService::class)->create($field, [
            'kind' => SafetyEventKind::Accident->value,
            'severity' => SafetyEventSeverity::High->value,
            'description' => 'Sturz von Leiter',
        ]);

        $this->assertSame(1, $lead->notifications()->count());
        $this->assertSame(1, NotificationDispatchLog::query()->withoutGlobalScopes()
            ->where('event', NotificationEvent::SafetyCriticalEvent->value)->count());
    }

    public function test_non_critical_event_does_not_notify(): void {
        NotificationRule::factory()->forEvent(NotificationEvent::SafetyCriticalEvent)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => false,
            'recipient_roles' => [\App\Enums\User\UserRole::Teamleitung->value, \App\Enums\User\UserRole::Admin->value],
        ]);
        $lead = $this->lead();

        app(SafetyEventService::class)->create($this->field(), [
            'kind' => SafetyEventKind::Hazard->value,
            'severity' => SafetyEventSeverity::Low->value,
            'description' => 'Kleinigkeit',
        ]);

        $this->assertSame(0, $lead->notifications()->count());
    }

    public function test_follow_up_creates_open_issue(): void {
        $lead = $this->lead();
        $service = app(SafetyEventService::class);
        $event = $service->create($lead, ['kind' => SafetyEventKind::Defect->value, 'description' => 'X']);

        $issue = $service->createFollowUpIssue($event, $lead, 'Geländer reparieren');

        $this->assertInstanceOf(OpenIssue::class, $issue);
        $this->assertDatabaseHas('open_issues', [
            'subject_type' => SafetyEvent::class,
            'subject_id' => $event->id,
            'title' => 'Geländer reparieren',
        ]);
    }

    public function test_field_staff_cannot_manage(): void {
        $event = SafetyEvent::factory()->create([
            'organization_id' => $this->organization->id,
            'reported_by_user_id' => $this->lead()->id,
        ]);

        $this->actingAs($this->field())
            ->post(route('safety-events.transition', $event), ['status' => SafetyEventStatus::Investigating->value])
            ->assertForbidden();
    }
}
