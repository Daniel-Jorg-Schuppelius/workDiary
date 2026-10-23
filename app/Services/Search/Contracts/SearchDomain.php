<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Contracts;

use App\Models\Platform\User;
use App\Services\Search\Dto\SearchDomainFilters;

/**
 * Erweiterungspunkt der globalen Suche (Feature 180): ein Modul meldet einen
 * Bereich mit Stammdaten oder Objekten über `Manifest::extensions()` an. Der
 * Bereich prüft Modul und Leserecht selbst und liefert nur Sichtbares.
 */
interface SearchDomain {
    /** Gruppen-Schlüssel, zugleich Wert des Bereichsfilters. */
    public function key(): string;

    public function label(): string;

    public function icon(): string;

    /** Position in der Trefferliste; kleinere Werte zuerst. */
    public function order(): int;

    public function available(User $user): bool;

    /**
     * Treffer zum Begriff. Einen Personen- oder Kundenfilter, den der Bereich
     * nicht kennt, beantwortet er mit „keine Treffer“, statt ihn zu übergehen.
     *
     * @return list<array{id: int|string, title: string, subtitle: string|null, url: string}>
     */
    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array;
}
