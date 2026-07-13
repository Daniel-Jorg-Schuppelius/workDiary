<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasMappableValues.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Capability-Interface (Rang 58, Muster InboxFirstSpec): Specs mit einer
 * Tag-/Kategorie-Spalte deklarieren sie hierüber — die Preflight sammelt
 * unbekannte Quellwerte, das Mapping-Formular ordnet sie zu (Tag oder
 * Klassifikation, A13), der Import wendet sie an (nie Blind-Neuanlage).
 */
interface HasMappableValues {
    /** Kanonischer Spaltenname der Mehrfachwerte (z. B. 'tags'). */
    public function mappableColumn(): string;

    /**
     * Zerlegt den Rohwert der Spalte in Einzelwerte (getrimmt, ohne Leere).
     *
     * @return list<string>
     */
    public function splitMappableValues(?string $raw): array;

    /**
     * Unbekannte Werte eines Rohwerts (weder Mapping noch Tag-Namens- oder
     * Klassifikations-Code-Treffer) — Datengrundlage des Mapping-Formulars.
     *
     * @return list<string>
     */
    public function unresolvedMappableValues(\App\Models\Organization $organization, ?string $raw, string $entity): array;
}
