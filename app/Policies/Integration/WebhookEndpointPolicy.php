<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookEndpointPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Integration;

use App\Enums\User\Permission as P;
use App\Models\Integration\WebhookEndpoint;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Webhook-Endpunkte (Feature 008): Admin verwaltet (HasAdminBypass),
 * webhook.viewAny zum Lesen, webhook.manage für CRUD/Rotation/Test.
 * Cross-Org-Zugriffe scheitern bereits am OrganizationScope des Modells;
 * die Policy prüft hier zusätzlich die Org-Zugehörigkeit defensiv.
 */
class WebhookEndpointPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::WebhookViewAny->value);
    }

    public function view(User $user, WebhookEndpoint $endpoint): bool {
        return $user->can(P::WebhookViewAny->value) && $this->sameOrg($user, $endpoint);
    }

    public function create(User $user): bool {
        return $user->can(P::WebhookManage->value);
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool {
        return $user->can(P::WebhookManage->value) && $this->sameOrg($user, $endpoint);
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool {
        return $user->can(P::WebhookManage->value) && $this->sameOrg($user, $endpoint);
    }

    private function sameOrg(User $user, WebhookEndpoint $endpoint): bool {
        return $user->organization_id !== null
            && (int) $user->organization_id === (int) $endpoint->organization_id;
    }
}
