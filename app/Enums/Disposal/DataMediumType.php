<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataMediumType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Disposal;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Datenträgertyp der Behandlung (Feature 100, MVP-475). Die Vorbelegung der
 * DIN-66399-Materialkategorie folgt der Norm-Zuordnung des Trägermaterials.
 */
enum DataMediumType: string implements HasLabel {
    use HasOptions;

    case Hdd = 'hdd';
    case Ssd = 'ssd';
    case UsbFlash = 'usb_flash';
    case MemoryCard = 'memory_card';
    case MobileDevice = 'mobile_device';
    case MagneticTape = 'magnetic_tape';
    case Optical = 'optical';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Hdd => (string) __('enums.disposal.data_medium_type.hdd'),
            self::Ssd => (string) __('enums.disposal.data_medium_type.ssd'),
            self::UsbFlash => (string) __('enums.disposal.data_medium_type.usb_flash'),
            self::MemoryCard => (string) __('enums.disposal.data_medium_type.memory_card'),
            self::MobileDevice => (string) __('enums.disposal.data_medium_type.mobile_device'),
            self::MagneticTape => (string) __('enums.disposal.data_medium_type.magnetic_tape'),
            self::Optical => (string) __('enums.disposal.data_medium_type.optical'),
            self::Other => (string) __('enums.disposal.data_medium_type.other'),
        };
    }

    /** Norm-Vorbelegung der DIN-66399-Materialkategorie je Trägermaterial. */
    public function defaultDinCategory(): DinCategory {
        return match ($this) {
            self::Hdd => DinCategory::H,
            self::Ssd, self::UsbFlash, self::MemoryCard, self::MobileDevice, self::Other => DinCategory::E,
            self::MagneticTape => DinCategory::T,
            self::Optical => DinCategory::O,
        };
    }
}
