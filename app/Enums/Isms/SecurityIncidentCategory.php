<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityIncidentCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Isms;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Kategorie eines Informationssicherheitsvorfalls (Feature 044, MVP 2). */
enum SecurityIncidentCategory: string implements HasLabel {
    use HasOptions;

    case Malware = 'malware';
    case Phishing = 'phishing';
    case DataLoss = 'dataLoss';
    case UnauthorizedAccess = 'unauthorizedAccess';
    case ServiceOutage = 'serviceOutage';
    case Misconfiguration = 'misconfiguration';
    case Physical = 'physical';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.isms.security-incident-category.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Malware, self::UnauthorizedAccess => 'error',
            self::Phishing => 'warning',
            self::DataLoss => 'secondary',
            self::ServiceOutage => 'primary',
            self::Misconfiguration => 'info',
            self::Physical => 'accent',
            self::Other => 'ghost',
        };
    }
}
