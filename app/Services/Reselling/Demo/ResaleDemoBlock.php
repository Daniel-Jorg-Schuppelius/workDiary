<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Demo;

use App\Enums\Reselling\{SubscriptionKind, SubscriptionProvider};
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Reselling\Register\PeriodPlanner;

/**
 * Demo-Block (Phase 175): drei von Hand geführte Abos des Hauptkunden mit
 * fälligen Perioden und ein Abo im eigenen Bestand, damit Überblick,
 * Arbeitsmappe, Arbeitsvorrat und Leitungssicht etwas zu zeigen haben.
 */
final class ResaleDemoBlock implements DemoBlock {
    private const string PREFIX = 'DEMO-';

    /** Kennung, Art, Bezeichnung, Menge, Beginn vor Monaten, Einkauf, Verkauf, eigener Bestand */
    private const array SUBSCRIPTIONS = [
        ['1', SubscriptionKind::License, 'Microsoft 365 Business Standard', 5, 14, '129.60', '162.00', false],
        ['2', SubscriptionKind::License, 'Exchange Online (Plan 1)', 3, 3, '43.20', '57.60', false],
        ['3', SubscriptionKind::Hosting, 'Webhosting Paket M', 1, 30, '71.40', '118.80', false],
        ['4', SubscriptionKind::License, 'Microsoft 365 Business Premium', 2, 8, '226.80', null, true],
    ];

    public function supports(DemoSeedContext $context): bool {
        return $context->moduleActive('module.reselling') && $context->mainCustomer !== null;
    }

    public function seed(DemoSeedContext $context): array {
        $today = ResalePeriod::today();
        $planner = new PeriodPlanner;
        $created = 0;
        foreach (self::SUBSCRIPTIONS as [$id, $kind, $label, $quantity, $monthsAgo, $purchase, $sale, $own]) {
            $subscription = ResaleSubscription::query()->withoutGlobalScopes()->firstOrCreate(
                ['organization_id' => $context->organization->id, 'provider' => SubscriptionProvider::Manual->value, 'external_id' => self::PREFIX . $id],
                [
                    'kind' => $kind, 'label' => $label, 'quantity' => $quantity,
                    'customer_id' => $own ? null : $context->mainCustomer?->id, 'is_own_holding' => $own,
                    'starts_on' => $today->subMonthsNoOverflow($monthsAgo)->toDateString(), 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
                    'purchase_unit_price' => $purchase, 'sale_unit_price' => $sale, 'currency' => 'EUR', 'status' => 'active',
                    'created_by_user_id' => $context->actor->id,
                ],
            );
            if ($subscription->wasRecentlyCreated) {
                $planner->sync($subscription);
                $created++;
            }
        }

        return ['resale_subscriptions' => $created];
    }

    public function purge(Organization $organization): void {
        $ids = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('provider', SubscriptionProvider::Manual->value)
            ->where('external_id', 'like', self::PREFIX . '%')
            ->pluck('id');
        ResalePeriod::query()->withoutGlobalScopes()->whereIn('subscription_id', $ids)->delete();
        ResaleSubscription::query()->withoutGlobalScopes()->whereKey($ids)->delete();
    }
}
