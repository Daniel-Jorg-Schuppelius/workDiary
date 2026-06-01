<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export;

use App\Enums\Export\ExportEntity;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;

/**
 * Vertrag für entitätsspezifische Export-Spezifikationen.
 *
 * Jede Implementierung kapselt:
 * - die kanonischen Spalten-Codes (deckungsgleich mit der Import-Spec,
 *   damit ein verlustfreier Round-Trip möglich ist),
 * - die mandantengebundene Abfrage inkl. optionaler Filter,
 * - die Projektion eines Models auf eine Export-Zeile.
 */
interface ExportSpec {
    public function entity(): ExportEntity;

    /**
     * Kanonische Spalten-Codes (Kopfzeile). Für Import-Entitäten identisch
     * mit {@see \App\Services\Import\EntitySpec::columns()}.
     *
     * @return list<string>
     */
    public function columns(): array;

    /**
     * Mandantengebundene, eager-geladene Query als Iterable (Cursor).
     *
     * @param  array<string, mixed>  $filters
     * @return iterable<int, Model>
     */
    public function query(Organization $organization, array $filters): iterable;

    /**
     * Projiziert ein Model auf {Spalten-Code => Wert}. Fehlende Spalten
     * werden vom Runner mit Leerstring aufgefüllt.
     *
     * @return array<string, string|int|float|null>
     */
    public function toRow(Model $model): array;
}
