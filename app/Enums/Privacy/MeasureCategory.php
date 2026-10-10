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

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Maßnahmenbereich/Schutzziel einer TOM (Art. 32, klassische Kontrollbereiche). */
enum MeasureCategory: string implements HasLabel {
    use HasOptions;

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
            self::PhysicalAccess => __('enums.privacy.measure_category.physical_access'),
            self::SystemAccess => __('enums.privacy.measure_category.system_access'),
            self::DataAccess => __('enums.privacy.measure_category.data_access'),
            self::Transfer => __('enums.privacy.measure_category.transfer'),
            self::Input => __('enums.privacy.measure_category.input'),
            self::Availability => __('enums.privacy.measure_category.availability'),
            self::Recovery => __('enums.privacy.measure_category.recovery'),
            self::Separation => __('enums.privacy.measure_category.separation'),
            self::Management => __('enums.privacy.measure_category.management'),
        };
    }
}
