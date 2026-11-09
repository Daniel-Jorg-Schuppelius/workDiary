<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseProductSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Search;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Reselling\ResaleLicenseProduct;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Database\Eloquent\Builder;

/** Lizenzprodukte des Lizenzbestands nach Name und Hersteller; Schlüssel sind nie Suchstoff. */
final class LicenseProductSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'resale_license_products';
    }

    public function label(): string {
        return (string) __('search.domain.resale_license_products');
    }

    public function icon(): string {
        return 'key';
    }

    public function order(): int {
        return 28;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('finance.resale.licenses.index') && $user->can(Permission::ResellingView->value);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        // Produkte haben weder Person, Kunde noch Datum.
        if ($filters->person !== null || $filters->customer !== null || $filters->from !== null || $filters->to !== null) {
            return [];
        }

        return array_values(ResaleLicenseProduct::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where(static fn(Builder $q) => $q->whereLikeEscaped('name', $term)->orWhereLikeEscaped('manufacturer', $term))
            ->orderBy('name')->limit($limit)->get()
            ->map(static fn(ResaleLicenseProduct $p): array => [
                'id' => $p->id,
                'title' => (string) $p->name,
                'subtitle' => $p->manufacturer !== null && $p->manufacturer !== '' ? (string) $p->manufacturer : null,
                'url' => route('finance.resale.licenses.index', ['product' => $p->sqid]),
            ])
            ->all());
    }
}
