<?php

/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StringFacade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Toolkit;

use CommonToolkit\Helper\Data\StringHelper;

/**
 * Dünner Wrapper um CommonToolkit\StringHelper.
 *
 * Vorteile gegenüber direkter Nutzung:
 * - Einheitlicher Einstiegspunkt für Strings im App-Code.
 * - Sinnvolle Defaults für deutschsprachige Inhalte.
 * - Erweiterbar um App-spezifische String-Operationen (z. B. printable_initials).
 */
final class StringFacade
{
    public static function isNullOrEmpty(?string $value): bool
    {
        return StringHelper::isNullOrEmpty($value);
    }

    public static function truncate(?string $text, int $maxLength, string $suffix = '...', bool $trim = false): string
    {
        return StringHelper::truncate($text, $maxLength, $suffix, $trim);
    }

    public static function mask(string $value, int $visibleStart = 3, int $visibleEnd = 3, string $maskChar = '*'): string
    {
        return StringHelper::mask($value, $visibleStart, $visibleEnd, $maskChar);
    }

    public static function normalizeWhitespace(?string $value): string
    {
        return StringHelper::normalizeWhitespace($value);
    }

    public static function sanitizePrintable(?string $value): string
    {
        return StringHelper::sanitizePrintable($value);
    }

    public static function stripBom(string $value): string
    {
        return StringHelper::stripBom($value);
    }

    public static function toUtf8(string $value, string $fromEncoding): string
    {
        return StringHelper::convertToUtf8($value, $fromEncoding);
    }

    /**
     * Datenschutz-freundliche Initialen, z. B. "Max Schuppelius" => "M.S.".
     * Wird in anonymisierten Print-Layouts genutzt.
     */
    public static function printableInitials(?string $name, int $maxParts = 3): string
    {
        if ($name === null || trim($name) === '') {
            return '—';
        }

        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $parts = array_slice($parts, 0, $maxParts);
        $initials = array_map(
            static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)).'.',
            $parts,
        );

        return implode('', $initials);
    }
}
