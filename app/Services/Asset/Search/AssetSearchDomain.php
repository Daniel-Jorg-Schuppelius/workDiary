<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset\Search;

use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/**
 * Objekte & Assets (Vollreview W5.2) nach Name sowie Asset-, Inventar- und
 * Seriennummer; kein Personenbezug, Kundenfilter über customer_id.
 */
final class AssetSearchDomain implements SearchDomain {
    public function key(): string {
        return 'assets';
    }

    public function label(): string {
        return (string) __('Objekte & Assets');
    }

    public function icon(): string {
        return 'precision_manufacturing';
    }

    public function order(): int {
        return 30;
    }

    public function available(User $user): bool {
        return Gate::forUser($user)->allows('viewAny', Asset::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $query = Asset::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('name', $term)
                ->orWhereLikeEscaped('asset_no', $term)
                ->orWhereLikeEscaped('inventory_no', $term)
                ->orWhereLikeEscaped('serial_no', $term))
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer))
            ->with('customer:id,name');
        $filters->range($query, 'created_at');

        return array_values($query->orderBy('name')->limit($limit)->get()
            ->map(fn (Asset $a) => [
                'id' => $a->id,
                'title' => (string) $a->name,
                'subtitle' => trim('#' . $a->asset_no
                    . ' · ' . $a->status->label()
                    . ($a->inventory_no ? ' · ' . $a->inventory_no : ($a->serial_no ? ' · ' . $a->serial_no : ''))
                    . ($a->customer ? ' · ' . $a->customer->name : '')),
                'url' => route('assets.show', $a),
            ])
            ->all());
    }
}
