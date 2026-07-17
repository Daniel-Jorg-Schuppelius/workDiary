<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataSubjectRequestType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Privacy;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art der Betroffenenanfrage (DSGVO Art. 15–21). */
enum DataSubjectRequestType: string implements HasLabel {
    use HasOptions;

    case Access = 'access';              // Art. 15 Auskunft
    case Rectification = 'rectification'; // Art. 16 Berichtigung
    case Erasure = 'erasure';            // Art. 17 Löschung
    case Restriction = 'restriction';    // Art. 18 Einschränkung
    case Portability = 'portability';    // Art. 20 Datenübertragbarkeit
    case Objection = 'objection';        // Art. 21 Widerspruch

    public function label(): string {
        return match ($this) {
            self::Access => __('Auskunft (Art. 15)'),
            self::Rectification => __('Berichtigung (Art. 16)'),
            self::Erasure => __('Löschung (Art. 17)'),
            self::Restriction => __('Einschränkung (Art. 18)'),
            self::Portability => __('Datenübertragbarkeit (Art. 20)'),
            self::Objection => __('Widerspruch (Art. 21)'),
        };
    }
}
