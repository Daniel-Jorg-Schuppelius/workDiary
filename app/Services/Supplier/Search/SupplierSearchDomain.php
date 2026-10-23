<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Supplier\Search;

use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Lieferanten nach Name, Firma, Nummer, Kreditorennummer und E-Mail; ohne Personen- und Kundenbezug. */
final class SupplierSearchDomain implements SearchDomain {
    public function key(): string {
        return 'suppliers';
    }

    public function label(): string {
        return (string) __('search.domain.suppliers');
    }

    public function icon(): string {
        return 'local_shipping';
    }

    public function order(): int {
        return 15;
    }

    public function available(User $user): bool {
        return Gate::forUser($user)->allows('viewAny', Supplier::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null || $filters->customer !== null) {
            return [];
        }
        $query = Supplier::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('name', $term)
                ->orWhereLikeEscaped('company', $term)
                ->orWhereLikeEscaped('number', $term)
                ->orWhereLikeEscaped('vendor_number', $term)
                ->orWhereLikeEscaped('email', $term));
        $filters->range($query, 'created_at');

        return array_values($query->orderBy('name')->limit($limit)->get()
            ->map(fn (Supplier $s) => [
                'id' => $s->id,
                'title' => (string) $s->name,
                'subtitle' => trim(($s->number ? '#' . $s->number : '') . ($s->email ? ' · ' . $s->email : '')) ?: null,
                'url' => route('suppliers.show', $s),
            ])
            ->all());
    }
}
