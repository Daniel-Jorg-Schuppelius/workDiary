<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareLicenseType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Software;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum SoftwareLicenseType: string implements HasLabel {
    use HasOptions;

    case Perpetual = 'perpetual';
    case Subscription = 'subscription';
    case Oem = 'oem';
    case Volume = 'volume';
    case Free = 'free';
    case OpenSource = 'open_source';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Perpetual    => __('enums.software.software_license_type.perpetual'),
            self::Subscription => __('enums.software.software_license_type.subscription'),
            self::Oem          => __('enums.software.software_license_type.oem'),
            self::Volume       => __('enums.software.software_license_type.volume'),
            self::Free         => __('enums.software.software_license_type.free'),
            self::OpenSource   => __('enums.software.software_license_type.open_source'),
            self::Other        => __('enums.software.software_license_type.other'),
        };
    }
}
