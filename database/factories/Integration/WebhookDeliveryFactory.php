<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookDeliveryFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Integration;

use App\Enums\Integration\{WebhookDeliveryStatus, WebhookEvent};
use App\Models\Integration\{WebhookDelivery, WebhookEndpoint};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory {
    protected $model = WebhookDelivery::class;

    public function definition(): array {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'organization_id' => fn(array $attrs): int => (int) WebhookEndpoint::query()
                ->withoutGlobalScopes()
                ->whereKey($attrs['webhook_endpoint_id'])
                ->value('organization_id'),
            'event' => WebhookEvent::OpenIssueAssigned->value,
            'payload_hash' => hash('sha256', fake()->uuid()),
            'status' => WebhookDeliveryStatus::Pending,
            'http_status' => null,
            'attempt' => 1,
            'dispatched_at' => now(),
        ];
    }

    public function success(): static {
        return $this->state(fn(): array => [
            'status' => WebhookDeliveryStatus::Success,
            'http_status' => 200,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static {
        return $this->state(fn(): array => [
            'status' => WebhookDeliveryStatus::Failed,
            'http_status' => 500,
            'completed_at' => now(),
        ]);
    }
}
