<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource, ServiceTicketStatus};
use App\Models\{Organization, ServiceTicket};
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceTicket> */
class ServiceTicketFactory extends Factory {
    protected $model = ServiceTicket::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'ticket_no' => strtoupper(fake()->bothify('ST-2026-#####')),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => ServiceTicketStatus::Reported->value,
            'priority' => ServiceTicketPriority::Normal->value,
            'source' => ServiceTicketSource::Manual->value,
            'reported_at' => now(),
        ];
    }
}
