<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationRuleAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Notification\NotificationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class NotificationRuleAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_regular_user_cannot_access_rules_index(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->get(route('admin.notification-rules.index'))
            ->assertForbidden();
    }

    public function test_teamleitung_can_view_but_not_update(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($teamlead)
            ->get(route('admin.notification-rules.index'))
            ->assertOk();

        $this->actingAs($teamlead)
            ->put(route('admin.notification-rules.update', ['event' => NotificationEvent::OpenIssueAssigned->value]), [
                'enabled' => '1',
                'notify_affected' => '1',
                'escalation_enabled' => '0',
            ])
            ->assertForbidden();
    }

    public function test_admin_sees_all_registry_events(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($admin)
            ->get(route('admin.notification-rules.index'))
            ->assertOk();

        foreach (NotificationEvent::cases() as $event) {
            $response->assertSee($event->value);
        }
    }

    public function test_admin_can_update_rule_and_row_is_persisted(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->put(route('admin.notification-rules.update', ['event' => NotificationEvent::OpenIssueOverdue->value]), [
                'enabled' => '1',
                'channels' => [NotificationChannel::InApp->value, NotificationChannel::Mail->value],
                'notify_affected' => '1',
                'recipient_roles' => ['teamleitung'],
                'escalation_enabled' => '1',
                'escalate_after_hours' => '4',
                'escalation_role' => 'teamleitung',
            ])
            ->assertRedirect(route('admin.notification-rules.index'));

        $rule = NotificationRule::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('event', NotificationEvent::OpenIssueOverdue->value)
            ->firstOrFail();

        $this->assertTrue($rule->enabled);
        $this->assertTrue($rule->escalation_enabled);
        $this->assertSame(4, $rule->escalate_after_hours);
        $this->assertSame('teamleitung', $rule->escalation_role);
        $this->assertSame(['teamleitung'], $rule->recipient_roles);
    }

    public function test_unknown_event_returns_404(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.notification-rules.edit', ['event' => 'does.notExist']))
            ->assertNotFound();
    }
}
