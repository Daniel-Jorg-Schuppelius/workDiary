<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract\Search;

use App\Models\Contract\Contract;
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Verträge nach Titel und Nummer; je Vertrag die Policy (Arbeitsverträge nur mit Personalakten-Recht). */
final class ContractSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'contracts';
    }

    public function label(): string {
        return (string) __('search.domain.contracts');
    }

    public function icon(): string {
        return 'contract';
    }

    public function order(): int {
        return 25;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('contracts.show') && Gate::forUser($user)->allows('viewAny', Contract::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $gate = Gate::forUser($user);
        $query = Contract::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('title', $term)->orWhereLikeEscaped('number', $term))
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer));
        $filters->range($query, 'starts_on', dateColumn: true);

        // Etwas mehr lesen: die Policy kann einzelne Verträge ausblenden.
        return array_values($query->orderBy('title')->limit($limit * 2)->get()
            ->filter(fn (Contract $c): bool => $gate->allows('view', $c))
            ->take($limit)
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'title' => (string) $c->title,
                'subtitle' => implode(' · ', array_filter([$c->number, $c->status->label()])) ?: null,
                'url' => route('contracts.show', $c),
            ])
            ->all());
    }
}
