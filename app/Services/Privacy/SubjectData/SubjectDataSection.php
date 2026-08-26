<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubjectDataSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein Abschnitt der DSGVO-Auskunft (Feature 129, Muster GoBD-Sections):
 * Stammdaten-Abschnitte liefern die direkten personenbezogenen Felder in
 * Vollausgabe, Familien-Abschnitte aggregierte Übersichten der
 * Verknüpfungsfamilien (Tabelle → Anzahl → Zeitraum) — bewusst KEINE
 * Massen-Rohdumps im MVP; je Familie kann später ein eigener Abschnitt
 * mit Rohzeilen ergänzt werden.
 */
interface SubjectDataSection {
    /** Stabiler Abschnittsschlüssel (z. B. `master_data`). */
    public function key(): string;

    /** Übersetzter Abschnittstitel für PDF/UI. */
    public function title(): string;

    /**
     * Art.-20-relevant? Nur Stammdaten-Abschnitte (vom Betroffenen
     * bereitgestellte Daten) landen in der Datenübertragbarkeits-CSV.
     */
    public function portable(): bool;

    /**
     * Abschnittsinhalt für das übergebene Betroffenen-Modell.
     *
     * - `fields`:   Maschinenschlüssel → {label, value} (Stammdaten-Vollausgabe)
     * - `lists`:    Listentitel → Zeilen (z. B. alle Adressen; nur JSON/PDF)
     * - `families`: aggregierte Verknüpfungsfamilien (Zähler + Zeitraum)
     *
     * @return array{
     *     fields?: array<string, array{label: string, value: string|null}>,
     *     lists?: array<string, list<array<string, string|null>>>,
     *     families?: list<array{table: string, label: string, count: int, from: string|null, to: string|null, details?: array<string, int|string>}>
     * }
     */
    public function build(Model $subject): array;
}
