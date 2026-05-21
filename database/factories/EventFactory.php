<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Event\EventStatus;
use App\Enums\Event\EventType;
use App\Enums\Event\EventVisibility;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory {
    protected $model = Event::class;

    public function definition(): array {
        $start = fake()->dateTimeBetween('-1 month', '+3 months');
        $end = (clone $start)->modify('+' . fake()->numberBetween(1, 8) . ' hours');

        return [
            'organization_id' => Organization::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'topic' => fake()->optional()->words(3, true),
            'event_type' => EventType::Training,
            'category_id' => null,
            'started_at' => $start,
            'ended_at' => $end,
            'is_all_day' => false,
            'timezone' => 'Europe/Berlin',
            'status' => EventStatus::Planned,
            'visibility' => EventVisibility::Internal,
            'responsible_user_id' => User::factory(),
            'customer_id' => null,
            'external_contact_note' => null,
            'max_participants' => null,
            'is_mandatory' => false,
            'certificate_valid_months' => null,
            'series_id' => null,
            'recurrence_rule' => null,
            'series_until' => null,
            'reminder_overrides' => null,
            'cancelled_at' => null,
            'cancel_reason' => null,
        ];
    }

    public function training(): self {
        return $this->state(fn(): array => [
            'event_type' => EventType::Training,
        ]);
    }

    public function mandatory(int $certificateValidMonths = 12): self {
        return $this->state(fn(): array => [
            'is_mandatory' => true,
            'certificate_valid_months' => $certificateValidMonths,
        ]);
    }

    public function recurring(string $rrule = 'FREQ=WEEKLY;COUNT=10'): self {
        return $this->state(fn(): array => [
            'recurrence_rule' => $rrule,
        ]);
    }

    public function cancelled(?string $reason = null): self {
        return $this->state(fn(): array => [
            'status' => EventStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }

    public function external(): self {
        return $this->state(fn(): array => [
            'visibility' => EventVisibility::External,
        ]);
    }
}
