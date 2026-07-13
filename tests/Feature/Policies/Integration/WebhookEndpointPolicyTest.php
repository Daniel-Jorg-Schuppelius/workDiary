<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookEndpointPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies\Integration;

use App\Enums\User\Permission as P;
use App\Models\Integration\WebhookEndpoint;
use App\Models\Organization;
use App\Policies\Integration\WebhookEndpointPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Webhook-Endpunkte (Feature 008): webhook.viewAny zum Lesen, webhook.manage
 * zum Verwalten; jeder Objektzugriff hart organisationsgebunden — fremde
 * Endpunkte (inkl. Secrets/Ziel-URLs) sind auch mit Recht unerreichbar.
 */
final class WebhookEndpointPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private WebhookEndpointPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new WebhookEndpointPolicy;
    }

    private function endpoint(?int $orgId = null): WebhookEndpoint {
        $endpoint = new WebhookEndpoint;
        $endpoint->organization_id = $orgId ?? $this->organization->id;

        return $endpoint;
    }

    public function test_viewer_reads_and_manager_writes(): void {
        $endpoint = $this->endpoint();

        $viewer = $this->actorIn($this->organization, [P::WebhookViewAny]);
        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertTrue($this->policy->view($viewer, $endpoint));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $endpoint));
        $this->assertFalse($this->policy->delete($viewer, $endpoint));

        $manager = $this->actorIn($this->organization, [P::WebhookManage]);
        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->update($manager, $endpoint));
        $this->assertTrue($this->policy->delete($manager, $endpoint));
    }

    public function test_foreign_org_endpoint_is_denied_even_with_permissions(): void {
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::WebhookViewAny, P::WebhookManage]);
        $endpoint = $this->endpoint(); // Primär-Org

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($attacker, $endpoint));
        $this->assertFalse($this->policy->update($attacker, $endpoint));
        $this->assertFalse($this->policy->delete($attacker, $endpoint));
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->actorIn($this->organization)));
        $orgless = $this->orglessActor();
        $this->assertFalse($this->policy->viewAny($orgless));
        $this->assertFalse($this->policy->view($orgless, $this->endpoint()));
    }
}
