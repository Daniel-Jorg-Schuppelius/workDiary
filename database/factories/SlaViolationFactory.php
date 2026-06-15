<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaViolationFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\ServiceTicket\SlaViolationKind;
use App\Models\{Organization, ServiceTicket, SlaViolation};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SlaViolation> */
class SlaViolationFactory extends Factory {
    protected $model = SlaViolation::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'service_ticket_id' => ServiceTicket::factory(),
            'sla_contract_id' => null,
            'kind' => SlaViolationKind::ResolutionTime->value,
            'target_at' => now()->subHours(2),
            'breached_at' => now()->subHour(),
            'overdue_minutes' => 60,
            'priority' => 'normal',
            'cause' => null,
        ];
    }

    public function responseTime(): static {
        return $this->state(fn(): array => ['kind' => SlaViolationKind::ResponseTime->value]);
    }

    public function acknowledged(): static {
        return $this->state(fn(): array => ['acknowledged_at' => now()]);
    }
}
