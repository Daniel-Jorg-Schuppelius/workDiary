<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Search;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Kunden nach Name, Nummer und E-Mail; kein Personenbezug, Kundenfilter entfällt. */
final class CustomerSearchDomain implements SearchDomain {
    public function key(): string {
        return 'customers';
    }

    public function label(): string {
        return (string) __('Kunden');
    }

    public function icon(): string {
        return 'badge';
    }

    public function order(): int {
        return 10;
    }

    public function available(User $user): bool {
        return Gate::forUser($user)->allows('viewAny', Customer::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null || $filters->customer !== null) {
            return [];
        }
        $query = Customer::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('name', $term)
                ->orWhereLikeEscaped('number', $term)
                ->orWhereLikeEscaped('email', $term));
        $filters->range($query, 'created_at');

        return array_values($query->orderBy('name')->limit($limit)->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'title' => (string) $c->name,
                'subtitle' => trim(($c->number ? '#' . $c->number : '') . ($c->email ? ' · ' . $c->email : '')) ?: null,
                'url' => route('customers.show', $c),
            ])
            ->all());
    }
}
