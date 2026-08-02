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
            self::Hdd => (string) __('Festplatte (HDD)'),
            self::Ssd => (string) __('SSD'),
            self::UsbFlash => (string) __('USB-Stick'),
            self::MemoryCard => (string) __('Speicherkarte'),
            self::MobileDevice => (string) __('Mobilgerät'),
            self::MagneticTape => (string) __('Magnetband'),
            self::Optical => (string) __('Optischer Datenträger'),
            self::Other => (string) __('Sonstiger Datenträger'),
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
