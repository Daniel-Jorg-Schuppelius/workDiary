<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleSubscriptionSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Search;

use App\Enums\User\Permission;
use App\Models\Customer\ForeignCustomer;
use App\Models\Platform\User;
use App\Models\Reselling\ResaleSubscription;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Database\Eloquent\Builder;

/** Abos (Lizenzen, Domains, Hosting) nach Produkt, Anbieter-Kennung, Lizenzkonto, Firma laut Anbieter und Halter. */
final class ResaleSubscriptionSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'resale_subscriptions';
    }

    public function label(): string {
        return (string) __('search.domain.resale_subscriptions');
    }

    public function icon(): string {
        return 'autorenew';
    }

    public function order(): int {
        return 27;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('finance.resale.show') && $user->can(Permission::ResellingView->value);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $query = ResaleSubscription::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id'])
            ->where(static fn(Builder $q) => $q->whereLikeEscaped('label', $term)
                ->orWhereLikeEscaped('external_id', $term)
                ->orWhereLikeEscaped('provider_account', $term)
                ->orWhereLikeEscaped('company_name', $term)
                ->orWhereHas('customer', static fn(Builder $c) => $c->whereLikeEscaped('name', $term))
                ->orWhereHas('foreignCustomer', static fn(Builder $f) => $f->whereLikeEscaped('name', $term)));
        if ($filters->customer !== null) {
            $customerId = $filters->customer;
            $query->where(static fn(Builder $w) => $w->where('customer_id', $customerId)
                ->orWhereIn('foreign_customer_id', ForeignCustomer::query()->withoutGlobalScopes()->where('customer_id', $customerId)->select('id')));
        }
        $filters->range($query, 'starts_on', dateColumn: true);

        // Laufende Abos zuerst, dann die jüngsten.
        return array_values($query->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderByDesc('starts_on')->limit($limit)->get()
            ->map(static fn(ResaleSubscription $s): array => [
                'id' => $s->id,
                'title' => (string) $s->label,
                'subtitle' => implode(' · ', array_filter([$s->holderLabel(), $s->identityLabel(), $s->status->label()])),
                'url' => route('finance.resale.show', $s->sqid),
            ])
            ->all());
    }
}
