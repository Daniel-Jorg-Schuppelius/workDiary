<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation;

use App\Models\Organization;

/**
 * Ein Abschnitt des generierten Systemteils der GoBD-Verfahrensdokumentation
 * (Feature 134, Muster GoBD-/SubjectData-Sections). Liefert Felder (Schlüssel
 * → Label/Wert), Tabellen und Hinweise — ausschließlich Anzeigewerte, nie
 * Secrets (der Builder weist Schlüssel mit Secret-Mustern ab).
 */
interface ProcedureSection {
    /** Stabiler Abschnittsschlüssel (z. B. `numbering`). */
    public function key(): string;

    /** Übersetzter Abschnittstitel für PDF/UI. */
    public function title(): string;

    /**
     * @return array{
     *     fields?: array<string, array{label: string, value: string}>,
     *     tables?: array<string, array{title: string, columns: list<string>, rows: list<list<string>>}>,
     *     notes?: list<string>
     * }
     */
    public function build(Organization $organization, SectionContext $context): array;
}
