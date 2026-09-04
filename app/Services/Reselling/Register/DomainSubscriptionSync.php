<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainSubscriptionSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Domain\DomainRenewalMode;
use App\Enums\Reselling\{BillingFrequency, RenewalMode, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Models\Domain\DomainProjection;
use App\Models\Organization;
use App\Models\Reselling\ResaleSubscription;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;

/**
 * Domains als Abo-Art (Feature 152, MVP-763): jede Domain-Projektion (083)
 * mit Halter wird ein Abo „Domain" — Anbieter DomainReselling, Kennung =
 * Domainname, Jahresintervall ab Registrierung, Einkauf = Verlängerungspreis
 * der Projektion. Die Projektion bleibt die technische Wahrheit; das Abo
 * spiegelt nur Halter, Laufzeit und Preis. Manuelle Verkaufspreise und
 * Halterentscheidungen im Register überlebt jeder Lauf.
 */
final class DomainSubscriptionSync {
    public function __construct(private readonly PeriodPlanner $planner) {}

    /**
     * @return array{domains: int, created: int, updated: int, unchanged: int, ended: int}
     */
    public function sync(Organization|int $organization, ?CarbonImmutable $reference = null): array {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $reference ??= CarbonImmutable::today();
        $result = ['domains' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'ended' => 0];
        $seen = [];

        $projections = DomainProjection::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('external_domain')
            ->get();
        foreach ($projections as $projection) {
            $result['domains']++;
            $externalId = mb_strtolower($projection->external_domain);
            $seen[] = $externalId;
            $startsOn = $projection->registration_at !== null ? CarbonImmutable::instance($projection->registration_at)->startOfDay() : null;
            if ($startsOn === null) {
                continue; // ohne Registrierungsdatum keine Perioden
            }
            $status = $this->status($projection, $reference);
            $endsOn = $status === SubscriptionStatus::Active ? null : ($projection->expiration_at !== null ? CarbonImmutable::instance($projection->expiration_at)->startOfDay()->toDateString() : $reference->toDateString());
            $attributes = [
                'kind' => SubscriptionKind::Domain,
                'label' => $projection->external_domain,
                'company_name' => null,
                'quantity' => 1,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn,
                'term_months' => 12,
                'interval' => BillingFrequency::Yearly,
                'renewal' => $projection->renewal_mode === DomainRenewalMode::Autorenew || $projection->renewal_mode === null ? RenewalMode::Auto : RenewalMode::Cancel,
                'purchase_unit_price' => $projection->renewal_price !== null ? (string) $projection->renewal_price : null,
                'currency' => $projection->renewal_currency !== null ? $projection->renewal_currency->value : 'EUR',
                'status' => $status,
                'domain_projection_id' => $projection->id,
            ];
            $hash = (string) CryptoHelper::hash(json_encode($attributes, JSON_THROW_ON_ERROR));

            $subscription = ResaleSubscription::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('provider', SubscriptionProvider::DomainReselling->value)
                ->where('external_id', $externalId)
                ->first();
            $isNew = $subscription === null;
            $subscription ??= new ResaleSubscription(['organization_id' => $organizationId, 'provider' => SubscriptionProvider::DomainReselling, 'external_id' => $externalId]);
            $unchanged = ! $isNew && $subscription->raw_hash === $hash;
            if (! $unchanged) {
                $subscription->fill($attributes);
                $subscription->raw_hash = $hash;
            }
            // Halter aus der Projektion, solange das Register keinen entschieden hat.
            if (! $subscription->hasHolder()) {
                $subscription->customer_id = $projection->customer_id;
                $subscription->foreign_customer_id = $projection->foreign_customer_id;
                $subscription->is_own_holding = (bool) $projection->is_own_holding;
            }
            $subscription->last_seen_at = CarbonImmutable::now();
            $subscription->save();
            $this->planner->sync($subscription, $reference);
            $result[$isNew ? 'created' : ($unchanged ? 'unchanged' : 'updated')]++;
        }

        // Domains, die aus der Projektion verschwunden sind, enden am Stichtag.
        $gone = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('provider', SubscriptionProvider::DomainReselling->value)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Cancelled->value])
            ->when($seen !== [], static fn($q) => $q->whereNotIn('external_id', $seen))
            ->get();
        foreach ($gone as $subscription) {
            $subscription->forceFill(['status' => SubscriptionStatus::Ended, 'ends_on' => $subscription->ends_on ?? $reference->toDateString()])->save();
            $this->planner->sync($subscription, $reference);
            $result['ended']++;
        }

        return $result;
    }

    private function status(DomainProjection $projection, CarbonImmutable $reference): SubscriptionStatus {
        $status = mb_strtoupper((string) $projection->status);
        if (in_array($status, ['DELETED', 'EXPIRED', 'TRANSFERRED_OUT', 'FAILED'], true)) {
            return SubscriptionStatus::Ended;
        }
        if ($projection->renewal_mode === DomainRenewalMode::Autoexpire || $projection->renewal_mode === DomainRenewalMode::Autodelete) {
            return $projection->expiration_at !== null && CarbonImmutable::instance($projection->expiration_at)->lessThan($reference) ? SubscriptionStatus::Ended : SubscriptionStatus::Cancelled;
        }

        return SubscriptionStatus::Active;
    }
}
