<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasureCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

/** Maßnahmenbereich/Schutzziel einer TOM (Art. 32, klassische Kontrollbereiche). */
enum MeasureCategory: string {
    case PhysicalAccess = 'physical_access'; // Zutrittskontrolle
    case SystemAccess = 'system_access';     // Zugangskontrolle
    case DataAccess = 'data_access';         // Zugriffskontrolle
    case Transfer = 'transfer';              // Weitergabekontrolle
    case Input = 'input';                    // Eingabekontrolle
    case Availability = 'availability';      // Verfügbarkeitskontrolle
    case Recovery = 'recovery';              // Wiederherstellbarkeit
    case Separation = 'separation';          // Trennungskontrolle
    case Management = 'management';          // Datenschutz-Management

    public function label(): string {
        return match ($this) {
            self::PhysicalAccess => __('Zutrittskontrolle'),
            self::SystemAccess => __('Zugangskontrolle'),
            self::DataAccess => __('Zugriffskontrolle'),
            self::Transfer => __('Weitergabekontrolle'),
            self::Input => __('Eingabekontrolle'),
            self::Availability => __('Verfügbarkeitskontrolle'),
            self::Recovery => __('Wiederherstellbarkeit'),
            self::Separation => __('Trennungskontrolle'),
            self::Management => __('Datenschutz-Management'),
        };
    }
}
