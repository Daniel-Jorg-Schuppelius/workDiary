<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeCheckPackage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Contracts;

use App\Enums\Sales\IntakeSensitivity;
use App\Models\Platform\Organization;
use App\Services\Fields\{FieldSchema, FieldValues};
use App\Services\Sales\Intake\Dto\IntakeRetentionRule;

/**
 * Erweiterungspunkt der Aufnahmeakte (Feature 200, MVP-1641): ein Prüfpaket
 * bringt Felder, Unterlagen, Aufbewahrung und Aktualisierung in die Akte.
 * Einfache Pakete kommen als Profildaten (Schlüssel `intake_packages`,
 * {@see \App\Services\Sales\Intake\Packages\ProfileIntakeCheckPackage});
 * Pakete mit Sonderlogik sind Klassen, die ein Modul über
 * `Manifest::extensions()` meldet (GwG, MVP-1642). Gelesen wird über
 * `ModuleExtensions::activeFor()` und nur, wenn die Organisation das Paket
 * eingeschaltet hat ({@see \App\Services\Sales\Intake\IntakePackages}).
 *
 * Feld- und Unterlagenschlüssel nennt das Paket ohne Präfix; die Akte setzt
 * `<paket>__` davor. Optional: {@see ChecksIntakeBeforeAcceptance} (eigene
 * Prüfung vor der Annahme), {@see ActsOnIntakeAcceptance} (Folge der Annahme).
 */
interface IntakeCheckPackage {
    /** Stabiler Schlüssel (`gwg`), Kleinbuchstaben, Ziffern, Unterstrich. */
    public function key(): string;

    public function label(): string;

    /** Felder des Pakets; Abschnittsfelder gliedern. */
    public function schema(): FieldSchema;

    /**
     * Unterlagenliste: Schlüssel → Bezeichnung (je ein Haken mit Anhängen).
     *
     * @return array<string, string>
     */
    public function documents(): array;

    /** Ohne Annahme keine Umwandlung des Leads, solange das Paket aktiv ist. */
    public function requiredBeforeDecision(): bool;

    /** Aufbewahrung der Akte (über Anonymisierung und Archivieren hinweg), null = keine eigene. */
    public function retention(): ?IntakeRetentionRule;

    /**
     * Aktualisierung nach der Annahme in Monaten (null = keine) — beim GwG
     * je Risikostufe aus der Organisationseinstellung.
     *
     * @param  FieldValues  $values  Werte des Pakets, Schlüssel ohne Präfix
     */
    public function refreshInterval(FieldValues $values, Organization $organization): ?int;

    /**
     * Felder und Unterlagen (`doc_<schlüssel>`) mit enger Sichtbarkeit.
     *
     * @return array<string, IntakeSensitivity>
     */
    public function sensitiveFields(): array;

    /**
     * Übernahme an den neuen Kunden bei der Umwandlung: Fristmerkmale
     * (Regelsatz → Merkmal → Feld, Feature 197), Klassifikationen (Domäne →
     * Feld, Wert = Code) und Kundenfelder als Vorschlag (Spalte → Feld).
     *
     * @return array{deadline_traits?: array<string, array<string, string>>, classifications?: array<string, string>, customer?: array<string, string>}
     */
    public function transfer(): array;
}
