<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SourceRow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Source;

use App\Services\Import\ValidationIssue;

/**
 * Eine von einer {@see ImportSource} gelieferte Zeile (MVP-438).
 *
 * Entweder eine **Datenzeile** (kanonische Feld-Map, keyed nach
 * {@see \App\Services\Import\EntitySpec::columns()}, die anschließend
 * `normalize()`/`validateRow()`/`upsert()` durchläuft) oder eine nicht
 * blockierende **Hinweiszeile** (`$warning` gesetzt, `$data` leer): iCal
 * überspringt z. B. Ganztags-/OOF-Events oder meldet nicht expandierte Serien,
 * ohne dass die Spezifikation etwas vom Format weiß.
 */
final readonly class SourceRow {
    /**
     * @param  int  $number  1-basierte laufende Nummer im Fehler-/Vorschaubericht
     * @param  array<string, string>  $data  kanonische Feld-Map (leer bei Hinweiszeilen)
     */
    public function __construct(
        public int $number,
        public array $data = [],
        public ?ValidationIssue $warning = null,
    ) {}

    public function isWarning(): bool {
        return $this->warning !== null;
    }

    /**
     * @param  array<string, string>  $data
     */
    public static function data(int $number, array $data): self {
        return new self($number, $data);
    }

    public static function warning(int $number, ValidationIssue $warning): self {
        return new self($number, [], $warning);
    }
}
