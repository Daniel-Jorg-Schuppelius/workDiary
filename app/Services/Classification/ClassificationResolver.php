<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Classification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Liefert effektive Klassifikationen pro Organisation und Domäne.
 *
 * Lookup-Logik (siehe docs/kernklassifikationen.md §4):
 *  1) Aktive Org-Datensätze (organization_id = $org) für $domain.
 *  2) Aktive Plattform-Defaults (organization_id = NULL), deren Code
 *     in (1) NICHT vorkommt → Org-Override-Mechanik.
 *  3) Sortiert nach sort_order, dann label.
 *
 * Caches die Ergebnisliste pro (org, domain) für 60 Sekunden.
 */
class ClassificationResolver {
    public const CACHE_TTL_SECONDS = 60;

    public static function cacheKey(?int $organizationId, ClassificationDomain $domain): string {
        return sprintf('classifications.v1.%s.%s', $organizationId ?? 'platform', $domain->value);
    }

    /**
     * @return Collection<int, Classification>
     */
    public function list(?int $organizationId, ClassificationDomain $domain): Collection {
        $cacheKey = self::cacheKey($organizationId, $domain);

        $ids = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            function () use ($organizationId, $domain): array {
                return $this->resolveIds($organizationId, $domain);
            },
        );

        if ($ids === []) {
            /** @var Collection<int, Classification> $empty */
            $empty = new Collection;

            return $empty;
        }

        /** @var Collection<int, Classification> $rows */
        $rows = Classification::query()
            ->whereIn('id', $ids)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return $rows;
    }

    public function resolveCode(?int $organizationId, ClassificationDomain $domain, string $code): ?Classification {
        return $this->list($organizationId, $domain)
            ->firstWhere('code', $code);
    }

    public function forget(?int $organizationId, ClassificationDomain $domain): void {
        Cache::forget(self::cacheKey($organizationId, $domain));
    }

    /**
     * @return list<int>
     */
    private function resolveIds(?int $organizationId, ClassificationDomain $domain): array {
        $orgRows = $organizationId === null
            ? collect()
            : Classification::query()
            ->where('organization_id', $organizationId)
            ->where('domain', $domain->value)
            ->where('active', true)
            ->get(['id', 'code']);

        $platformRows = Classification::query()
            ->whereNull('organization_id')
            ->where('domain', $domain->value)
            ->where('active', true)
            ->get(['id', 'code']);

        $overrideCodes = $orgRows->pluck('code')->all();
        $effective = $orgRows
            ->concat($platformRows->reject(fn($row) => in_array($row->code, $overrideCodes, true)));

        return array_values(array_map(static fn($row): int => (int) $row->id, $effective->all()));
    }
}
