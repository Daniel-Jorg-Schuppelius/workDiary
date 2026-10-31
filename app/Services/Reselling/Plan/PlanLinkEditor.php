<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanLinkEditor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Plan;

use App\Enums\Reselling\LinkOrigin;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePlanLink, ResaleSubscription};

/**
 * Zuordnung Vorlagenposition ↔ Abo von Hand (Feature 185, MVP-1412; seit
 * Phase 165 aus Lizenzkonto und Arbeitsmappe): ein Vorschlag wird bestätigt,
 * sonst entsteht eine manuelle Zuordnung; Lösen nimmt sie weg. Beides mit Audit.
 */
final class PlanLinkEditor {
    public function save(Organization $organization, ResaleSubscription $subscription, string $planKey, int $position, int $quantity, ?int $userId): ResalePlanLink {
        $link = ResalePlanLink::query()->firstOrNew(['subscription_id' => $subscription->id, 'plan_key' => $planKey, 'line_position' => $position]);
        $link->fill([
            'organization_id' => $organization->id,
            'quantity' => $quantity,
            'origin' => $link->exists && $link->origin === LinkOrigin::Proposed ? LinkOrigin::Confirmed : ($link->exists ? $link->origin : LinkOrigin::Manual),
            'confirmed_at' => now(),
            'created_by' => $link->exists ? $link->created_by : $userId,
        ])->save();
        $link->audit('resale_plan_link.saved', ['plan' => $link->plan_key, 'position' => $link->line_position, 'quantity' => $link->quantity, 'subscription' => $subscription->label]);

        return $link;
    }

    public function remove(ResalePlanLink $link): void {
        $link->audit('resale_plan_link.removed', ['plan' => $link->plan_key, 'position' => $link->line_position, 'quantity' => $link->quantity]);
        $link->delete();
    }
}
