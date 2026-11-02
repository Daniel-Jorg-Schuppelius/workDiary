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
    public function __construct(private readonly RecurringPlans $plans) {}

    /**
     * @throws \InvalidArgumentException Vorlagenposition unbekannt oder mehr Lizenzen, als sie noch frei hat (Phase 167, MVP-1454)
     */
    public function save(Organization $organization, ResaleSubscription $subscription, string $planKey, int $position, int $quantity, ?int $userId): ResalePlanLink {
        $free = $this->freeLicences($organization, $subscription, $planKey, $position);
        if ($quantity > $free) {
            throw new \InvalidArgumentException((string) __('resale.plan.error.quantity', ['free' => $free]));
        }
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

    /**
     * Lizenzen der Vorlagenposition, die noch keinem anderen Abo zugeordnet sind — die bisherige
     * Zuordnung dieses Abos zählt nicht, weil die neue sie ersetzt; ohne Abo (noch anzulegen,
     * sec-04) zählen alle bestehenden Zuordnungen.
     *
     * @throws \InvalidArgumentException Vorlage oder Position unbekannt
     */
    public function freeLicences(Organization $organization, ?ResaleSubscription $subscription, string $planKey, int $position): int {
        $template = $this->plans->templateByKey($organization, $planKey);
        $line = $template?->line($position);
        if ($template === null || $line === null) {
            throw new \InvalidArgumentException((string) __('resale.plan.error.line'));
        }
        $ownId = $subscription?->id;
        $taken = (int) ResalePlanLink::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plan_key', $planKey)->where('line_position', $position)
            ->when($ownId !== null, static fn($q) => $q->where('subscription_id', '!=', $ownId))
            ->sum('quantity');

        return max(0, (int) round($line->licences($template->intervalMonths())) - $taken);
    }

    public function remove(ResalePlanLink $link): void {
        $link->audit('resale_plan_link.removed', ['plan' => $link->plan_key, 'position' => $link->line_position, 'quantity' => $link->quantity]);
        $link->delete();
    }
}
