<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan\Dto;

use App\Services\Fields\{FieldDefinition, FieldValues};

/**
 * Ergebnis des Leistungsplans: Spalten, Zeilen, Zähler, angebotene Leistungen
 * und — in der Periodensicht — die Merkmale des gewählten Regelsatzes als
 * Zusatzspalten (Feld `listed` des Feldschemas, Feature 197).
 */
final readonly class ServicePlanResult {
    /**
     * @param  list<ServicePlanColumn>  $columns  angezeigte Leistungen (Jahressicht: Spalten; Periodensicht: Leistungen der Zeilen)
     * @param  list<ServicePlanRow>  $rows
     * @param  array<string, int>  $counts  Filtergruppe → Anzahl Zellen
     * @param  list<ServicePlanColumn>  $available  alle Leistungen der Sicht (Spaltenauswahl)
     * @param  array<int, string>  $responsibleNames  Personen-ID → Name
     * @param  list<FieldDefinition>  $traitFields  Zusatzspalten aus dem Regelsatz
     * @param  array<int, FieldValues>  $traits  Kunde → Merkmale der geltenden Fassung
     */
    public function __construct(
        public ServicePlanQuery $query,
        public array $columns,
        public array $rows,
        public array $counts,
        public array $available,
        public array $responsibleNames = [],
        public array $traitFields = [],
        public array $traits = [],
    ) {}

    public function responsibleName(?int $userId): ?string {
        return $userId !== null ? ($this->responsibleNames[$userId] ?? null) : null;
    }

    public function traitsOf(int $customerId): ?FieldValues {
        return $this->traits[$customerId] ?? null;
    }
}
