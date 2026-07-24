<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookEndpointFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Integration;

use App\Enums\Integration\WebhookEvent;
use App\Models\Integration\WebhookEndpoint;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory {
    protected $model = WebhookEndpoint::class;

    public function definition(): array {
        return [
            'organization_id' => Organization::factory(),
            'label' => fake()->words(2, true),
            // Feste reservierte Doku-Domain (RFC 2606) statt fake()->domainName():
            // Der Delivery-Job löst den Host zur Laufzeit über den SSRF-Guard
            // (UrlSafety::isPubliclyRoutableHttpUrl → echtes DNS) auf. Eine
            // Zufallsdomain kann (je nach Resolver/NXDOMAIN-Hijack) auf eine
            // nicht-öffentliche IP zeigen → Guard blockt → Delivery „Failed"
            // (flaky, ordnungsunabhängig). example.com löst stets auf eine
            // öffentliche IP auf (oder offline gar nicht → Guard lässt durch).
            'url' => 'https://example.com/hooks/workdiary',
            'secret' => WebhookEndpoint::generateSecret(),
            'events' => [WebhookEvent::OpenIssueAssigned->value],
            'active' => true,
            'consecutive_failures' => 0,
        ];
    }

    public function inactive(): static {
        return $this->state(fn(): array => ['active' => false]);
    }

    public function disabled(): static {
        return $this->state(fn(): array => [
            'active' => false,
            'disabled_at' => now(),
            'consecutive_failures' => WebhookEndpoint::MAX_CONSECUTIVE_FAILURES,
        ]);
    }

    /** @param  list<WebhookEvent>  $events */
    public function subscribedTo(array $events): static {
        return $this->state(fn(): array => [
            'events' => array_map(fn(WebhookEvent $e): string => $e->value, $events),
        ]);
    }
}
