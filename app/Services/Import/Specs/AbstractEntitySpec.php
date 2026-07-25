<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractEntitySpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\ImportErrorCode;
use App\Services\Import\{EntitySpec, ValidationIssue};
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Basisklasse mit gemeinsamen Validierungs-Helfern für CSV-Import-Spezifikationen.
 */
abstract class AbstractEntitySpec implements EntitySpec {
    /**
     * Standard-Vorverarbeitung: keine. Spezifikationen mit anbieterspezifischen
     * Datei-Eigenheiten (z. B. Excel-`sep=`-Vorzeile) überschreiben dies.
     */
    public function preprocessRaw(string $raw): string {
        return $raw;
    }

    protected function trimmedString(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }

    protected function boolish(mixed $value): bool {
        $v = mb_strtolower(trim((string) $value));

        return in_array($v, ['1', 'ja', 'yes', 'true', 'wahr', 'y', 'j'], true);
    }

    protected function upperOrNull(?string $value): ?string {
        return $value === null ? null : mb_strtoupper($value);
    }

    protected function lowerOrNull(?string $value): ?string {
        return $value === null ? null : mb_strtolower($value);
    }

    /**
     * Dezimalnormalisierung über den Toolkit-Standard (Vollaudit 2026-07,
     * M49): NumberHelper::normalizeDecimalString entscheidet DE/US über die
     * LETZTE Separator-Position — die frühere Eigenheuristik verparste
     * US-Format ("1,234.56" → 1.23456). Randsemantik bleibt App-Sache:
     * null/leer → null (Toolkit lieferte "0"), nicht Numerisches → null.
     */
    protected function decimal(?string $value): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // OrNull-Variante: unterscheidet nicht deutbaren Input ("n/a") von einer
        // echten Null. normalizeDecimalString() würde beides zu '0' machen.
        return NumberHelper::normalizeDecimalStringOrNull(str_replace("\u{00A0}", '', $value));
    }

    protected function requiredIssue(string $field): ValidationIssue {
        return new ValidationIssue(
            ImportErrorCode::Required,
            $field,
            (string) __('import.error.required', ['field' => $field]),
        );
    }

    protected function formatIssue(string $field, string $reason): ValidationIssue {
        return new ValidationIssue(
            ImportErrorCode::Format,
            $field,
            (string) __('import.error.format.default', ['field' => $field, 'reason' => $reason]),
        );
    }

    protected function tooLongIssue(string $field, int $max): ValidationIssue {
        return new ValidationIssue(
            ImportErrorCode::TooLong,
            $field,
            (string) __('import.error.tooLong', ['field' => $field, 'max' => $max]),
        );
    }
}
