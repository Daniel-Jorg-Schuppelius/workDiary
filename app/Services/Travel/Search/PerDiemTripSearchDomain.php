<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Travel\Search;

use App\Models\Platform\User;
use App\Models\Travel\PerDiemTrip;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;
use CommonToolkit\Helper\Data\StringHelper;

/** Reisen nach Ort, Zweck und Land; eigene, alle nur für Admins (wie Spesen). Kein Kundenbezug. */
final class PerDiemTripSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'per_diem_trips';
    }

    public function label(): string {
        return (string) __('Reisekosten');
    }

    public function icon(): string {
        return 'flight';
    }

    public function order(): int {
        return 50;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('per-diem-trips.show');
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->customer !== null) {
            return [];
        }
        $query = PerDiemTrip::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('location', $term)
                ->orWhereLikeEscaped('purpose', $term)
                ->orWhereLikeEscaped('country', $term))
            ->when($filters->person !== null, fn ($q) => $q->where('user_id', $filters->person));
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }
        $filters->range($query, 'started_at');

        return array_values($query->orderByDesc('started_at')->limit($limit)->get()
            ->map(fn (PerDiemTrip $t) => [
                'id' => $t->id,
                'title' => trim(($t->location ?: '—') . ($t->country ? ' (' . $t->country . ')' : '')),
                'subtitle' => CarbonFmt::fdate(CarbonFmt::orgTz($t->started_at))
                    . ($t->purpose ? ' · ' . StringHelper::truncate($t->purpose, 60, '…') : ''),
                'url' => route('per-diem-trips.show', $t),
            ])
            ->all());
    }
}
