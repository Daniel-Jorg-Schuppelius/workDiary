<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalSearchService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\{CarbonFmt, OrganizationContext};

/**
 * Gemeinsame Treffer-Queries der globalen Suche (MVP-014, Feature 023):
 * Command-Palette (Limit 5 je Gruppe) UND Vollergebnisseite `/suche`
 * (Vollaudit 2026-07, M8) nutzen exakt dieselben rechte- und org-sicheren
 * Gruppen — keine zweite Sichtbarkeitslogik.
 *
 * Seit Feature 153 kommen Aufträge, Kommunikation, Wissen und Kommentare
 * (plus Zeiten, Stundenzettel, Tickets, Protokolle, offene Punkte) aus dem
 * Tätigkeitsindex ({@see ActivitySearchService}) als Gruppe „Tätigkeiten";
 * Stammdaten und Objekte melden die Module als {@see SearchDomain} an
 * (Feature 180) — jeder Bereich prüft Modul und Leserecht selbst.
 *
 * Filter (M8): Domäne (Gruppen-Key), Zeitraum (domänenspezifische
 * Datumsspalte), Person und Kunde. Gruppen ohne Personen-/Kundenbezug liefern
 * bei gesetztem Personen-/Kundenfilter bewusst keine Treffer (ehrlich statt
 * still ignoriert).
 *
 * Anzeige-Zeitstempel (W4.2): laufen über {@see CarbonFmt} — datetime-Spalten
 * (UTC) erst via orgTz() in die Anzeige-Zeitzone, reine date-Casts direkt fdate().
 *
 * @phpstan-type SearchItem array{id: int|string, title: string, subtitle: string|null, url: string}
 * @phpstan-type SearchGroup array{key: string, label: string, icon: string, items: list<array{id: int|string, title: string, subtitle: string|null, url: string}>}
 * @phpstan-type SearchFilters array{domain?: string|null, from?: string|null, to?: string|null, person?: int|null, customer?: int|null}
 */
class GlobalSearchService {
    public const ACTIVITIES = 'activities';

    public function __construct(
        private readonly ActivitySearchService $activities,
        private readonly ModuleRegistry $modules,
    ) {}

    /**
     * Für den Nutzer verfügbare Domänen (Key => Label) für die Filter-UI.
     *
     * @return array<string, string>
     */
    public function domains(User $user): array {
        $domains = [self::ACTIVITIES => (string) __('search.group.activities')];
        foreach ($this->availableDomains($user) as $domain) {
            $domains[$domain->key()] = $domain->label();
        }

        return $domains;
    }

    /**
     * Alle sichtbaren Treffergruppen für den Begriff (leer gefilterte Gruppen
     * werden entfernt). Die Vollergebnisseite zeigt die Tätigkeiten selbst
     * und fragt deshalb ohne sie.
     *
     * @param  SearchFilters  $filters
     * @return list<SearchGroup>
     */
    public function groups(User $user, string $term, array $filters = [], int $limit = 5, bool $withActivities = true): array {
        $orgId = OrganizationContext::currentId() ?? $user->organization_id;
        $domainFilter = $filters['domain'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $person = $filters['person'] ?? null;
        $customer = $filters['customer'] ?? null;

        $groups = [];

        // Tätigkeiten (Feature 153): Zeiten, Aufträge inkl. Kommentare,
        // Stundenzettel, Tickets, Protokolle, offene Punkte, Kommunikation,
        // Wissen — Rechte je Quelle im ActivitySearchVisibility.
        if ($withActivities && ($domainFilter === null || $domainFilter === self::ACTIVITIES)) {
            $groups[] = $this->makeGroup(
                self::ACTIVITIES,
                (string) __('search.group.activities'),
                'manage_search',
                $this->activities->typeAhead($user, $term, ['from' => $from, 'to' => $to, 'person' => $person, 'customer' => $customer], $limit),
            );
        }

        // Ohne Organisation keine Stammdaten — fail-closed.
        if ($orgId !== null) {
            $domainFilters = new SearchDomainFilters($from, $to, $person, $customer);
            foreach ($this->availableDomains($user) as $domain) {
                if ($domainFilter !== null && $domainFilter !== $domain->key()) {
                    continue;
                }
                $groups[] = $this->makeGroup($domain->key(), $domain->label(), $domain->icon(), $domain->search($user, (int) $orgId, $term, $domainFilters, $limit));
            }
        }

        // Leere Gruppen entfernen.
        return array_values(array_filter($groups, static fn(array $g): bool => count($g['items']) > 0));
    }

    /** @return list<SearchDomain> nach {@see SearchDomain::order()} */
    private function availableDomains(User $user): array {
        $domains = [];
        foreach ($this->modules->extensions(SearchDomain::class) as $class) {
            /** @var SearchDomain $domain */
            $domain = app($class);
            if ($domain->available($user)) {
                $domains[] = $domain;
            }
        }
        usort($domains, static fn (SearchDomain $a, SearchDomain $b): int => $a->order() <=> $b->order());

        return $domains;
    }

    /**
     * @param  array<int, array{id: int|string, title: string, subtitle: string|null, url: string}>  $items
     * @return SearchGroup
     */
    private function makeGroup(string $key, string $label, string $icon, array $items): array {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'items' => array_values($items),
        ];
    }
}
