<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchSynonyms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\SearchSynonymGroup;

/**
 * Aktive Synonymgruppen einer Organisation als Nachschlagetabelle
 * (Feature 153, MVP-772): Vergleichsform eines Begriffs → die anderen
 * Begriffe seiner Gruppen als Wortfolgen. Scoped gebunden.
 */
final class SearchSynonyms {
    /** @var array<int, array<string, list<list<string>>>> */
    private array $maps = [];

    public function __construct(private readonly SearchTextNormalizer $normalizer) {}

    /** @return array<string, list<list<string>>> */
    public function mapFor(int $organizationId): array {
        return $this->maps[$organizationId] ??= $this->load($organizationId);
    }

    public function flush(): void {
        $this->maps = [];
    }

    /** @return array<string, list<list<string>>> */
    private function load(int $organizationId): array {
        $map = [];
        $groups = SearchSynonymGroup::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->get(['terms']);

        foreach ($groups as $group) {
            $keys = [];
            foreach ((array) $group->terms as $term) {
                $key = $this->normalizer->key((string) $term);
                if ($key !== '') {
                    $keys[$key] = true;
                }
            }
            foreach (array_keys($keys) as $key) {
                foreach (array_keys($keys) as $other) {
                    if ($other !== $key) {
                        // Zahlbegriffe ("4711") werden als Array-Schlüssel zu int.
                        $map[$key][$other] = explode(' ', (string) $other);
                    }
                }
            }
        }

        return array_map(static fn(array $alternatives): array => array_values($alternatives), $map);
    }
}
