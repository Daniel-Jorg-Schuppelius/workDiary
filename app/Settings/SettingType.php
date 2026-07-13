<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Settings;

/**
 * Werttyp einer registrierten Einstellung (Feature 067, MVP-173).
 * Liefert die Typ-Grundvalidierung; fachliche Grenzen kommen aus
 * SettingDefinition::$rules.
 */
enum SettingType: string {
    case String_ = 'string';
    case Text = 'text';     // mehrzeiliger Freitext (Textarea)
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Enum_ = 'enum';
    case Json = 'json';
    case Time = 'time';     // "HH:MM"
    case Duration = 'duration'; // Minuten als Ganzzahl

    /** Grundvalidierung, die der fachlichen rules-Angabe vorangestellt wird. */
    public function baseRule(): string {
        return match ($this) {
            self::String_, self::Text, self::Enum_ => 'string',
            self::Integer, self::Duration => 'integer',
            self::Decimal => 'numeric',
            self::Boolean => 'boolean',
            self::Json => 'array',
            self::Time => 'date_format:H:i',
        };
    }
}
