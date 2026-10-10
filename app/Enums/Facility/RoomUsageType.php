<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomUsageType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Facility;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

enum RoomUsageType: string implements HasLabel {
    use HasOptions;

    case Office = 'office';
    case ServerRoom = 'server_room';
    case Cleanroom = 'cleanroom';
    case Kitchen = 'kitchen';
    case Sanitary = 'sanitary';
    case Lab = 'lab';
    case Storage = 'storage';
    case TrafficArea = 'traffic_area';
    case Meeting = 'meeting';
    case Social = 'social';
    case Technical = 'technical';
    case Outdoor = 'outdoor';
    case Other = 'other';

    public function label(): string {
        return match ($this) {
            self::Office => (string) __('enums.facility.room_usage_type.office'),
            self::ServerRoom => (string) __('enums.facility.room_usage_type.server_room'),
            self::Cleanroom => (string) __('enums.facility.room_usage_type.cleanroom'),
            self::Kitchen => (string) __('enums.facility.room_usage_type.kitchen'),
            self::Sanitary => (string) __('enums.facility.room_usage_type.sanitary'),
            self::Lab => (string) __('enums.facility.room_usage_type.lab'),
            self::Storage => (string) __('enums.facility.room_usage_type.storage'),
            self::TrafficArea => (string) __('enums.facility.room_usage_type.traffic_area'),
            self::Meeting => (string) __('enums.facility.room_usage_type.meeting'),
            self::Social => (string) __('enums.facility.room_usage_type.social'),
            self::Technical => (string) __('enums.facility.room_usage_type.technical'),
            self::Outdoor => (string) __('enums.facility.room_usage_type.outdoor'),
            self::Other => (string) __('enums.facility.room_usage_type.other'),
        };
    }
}
