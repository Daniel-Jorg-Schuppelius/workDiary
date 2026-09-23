<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationRuleFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Notification\NotificationRule;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationRule>
 */
class NotificationRuleFactory extends Factory {
    protected $model = NotificationRule::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'event' => NotificationEvent::OpenIssueAssigned->value,
            'enabled' => true,
            'channels' => [NotificationChannel::InApp->value, NotificationChannel::Mail->value],
            'notify_affected' => true,
            'recipient_roles' => [],
            'recipient_user_ids' => [],
            'escalation_enabled' => false,
            'escalate_after_hours' => null,
            'escalation_role' => null,
            'escalation2_after_hours' => null,
            'escalation2_roles' => [],
            'escalation2_user_ids' => [],
            'escalation3_after_hours' => null,
            'escalation3_roles' => [],
            'escalation3_user_ids' => [],
        ];
    }

    public function forEvent(NotificationEvent $event): self {
        return $this->state(fn() => ['event' => $event->value]);
    }
}
