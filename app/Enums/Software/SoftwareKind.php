<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Software;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum SoftwareKind: string implements HasLabel {
    use HasOptions;

    case OperatingSystem = 'operating_system';
    case Application = 'application';
    case Firmware = 'firmware';
    case Driver = 'driver';
    case Service = 'service';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::OperatingSystem => __('Betriebssystem'),
            self::Application     => __('Anwendung'),
            self::Firmware        => __('Firmware'),
            self::Driver          => __('Treiber'),
            self::Service         => __('Dienst'),
            self::Other           => __('Sonstige'),
        };
    }
}
