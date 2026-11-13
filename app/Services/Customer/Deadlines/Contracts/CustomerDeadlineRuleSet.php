<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineRuleSet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Deadlines\Contracts;

use App\Models\Customer\Customer;
use App\Services\Customer\Deadlines\Dto\DeadlineSpec;
use App\Services\Fields\{FieldSchema, FieldValues};

/**
 * Erweiterungspunkt der Kundenfristen (Feature 197, MVP-1630): ein Regelsatz
 * rechnet gesetzliche Fristen aus Merkmalen eines Kunden und seinem
 * Leistungsumfang (`customer_services`). Regeln sind Code mit Tests und
 * Quelle, keine Formel. Ein Modul meldet seinen Regelsatz über
 * `Manifest::extensions()`; gelesen wird über
 * `ModuleExtensions::activeFor()` und nur, wenn die Organisation den Regelsatz
 * eingeschaltet hat ({@see \App\Services\Customer\Deadlines\CustomerDeadlineRuleSets}).
 *
 * Merkmale, die es als Klassifikation schon gibt (Pflegegrad), stehen nicht im
 * Schema; der Regelsatz liest sie am Kunden und zeigt sie über
 * {@see derivedTraits()} an.
 */
interface CustomerDeadlineRuleSet {
    /** Stabiler Schlüssel (`tax_advisory`), gespeichert in Profil und Frist. */
    public function key(): string;

    /** Bezeichnung in Dialog, Filter und Übersicht. */
    public function label(): string;

    /**
     * Fristarten des Regelsatzes: Schlüssel → Bezeichnung. Zusammengesetzte
     * Arten (`annual_return:einkommensteuer`) löst {@see kindLabel()} auf.
     *
     * @return array<string, string>
     */
    public function kinds(): array;

    /** Bezeichnung einer Fristart, auch einer zusammengesetzten. */
    public function kindLabel(string $kind): string;

    /** Merkmale des Kunden als Feldschema — Dialog, Prüfung, Anzeige und Zusatzspalten des Leistungsplans. */
    public function traitSchema(): FieldSchema;

    /**
     * Merkmale aus anderen Quellen (Klassifikation), nur zur Anzeige.
     *
     * @return array<string, string> Bezeichnung → Wert
     */
    public function derivedTraits(Customer $customer): array;

    /**
     * Fristen der Perioden des Jahres $year (Periodenbeginn im Jahr; die
     * Jahreserklärung des Jahres $year ist erst im übernächsten fällig).
     *
     * @return list<DeadlineSpec>
     */
    public function deadlines(Customer $customer, FieldValues $traits, int $year): array;

    /**
     * Erledigt ein abgeschlossener Auftrag dieser Eintragsart (Slug) und
     * Produktgruppe (Code, null = keine) die Frist der Art in derselben Periode?
     */
    public function completes(string $kind, string $entryTypeSlug, ?string $productGroup): bool;
}
