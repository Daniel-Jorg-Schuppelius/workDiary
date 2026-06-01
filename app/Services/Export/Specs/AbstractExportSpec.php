<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractExportSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export\Specs;

use App\Services\Export\ExportSpec;
use DateTimeInterface;

/**
 * Basisklasse mit gemeinsamen Projektions-Helfern für Export-Spezifikationen.
 */
abstract class AbstractExportSpec implements ExportSpec {
    /**
     * Bool → 1/0 (leer bei null), passend zur boolish()-Logik des Imports.
     */
    protected function boolCell(?bool $value): string {
        if ($value === null) {
            return '';
        }

        return $value ? '1' : '0';
    }

    /**
     * Dezimalwert mit Punkt als Trennzeichen (round-trip-fähig). Leer bei null.
     */
    protected function decimalCell(int|float|string|null $value): string {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) $value;
    }

    protected function dateCell(?DateTimeInterface $value, string $format = 'Y-m-d'): string {
        return $value?->format($format) ?? '';
    }

    protected function str(mixed $value): string {
        return $value === null ? '' : (string) $value;
    }
}
