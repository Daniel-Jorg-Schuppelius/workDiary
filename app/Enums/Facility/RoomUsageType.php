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

enum RoomUsageType: string {
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
            self::Office => (string) __('Büro'),
            self::ServerRoom => (string) __('Serverraum'),
            self::Cleanroom => (string) __('Reinraum'),
            self::Kitchen => (string) __('Küche'),
            self::Sanitary => (string) __('Sanitär'),
            self::Lab => (string) __('Labor'),
            self::Storage => (string) __('Lager'),
            self::TrafficArea => (string) __('Verkehrsfläche'),
            self::Meeting => (string) __('Besprechung'),
            self::Social => (string) __('Sozialraum'),
            self::Technical => (string) __('Technikraum'),
            self::Outdoor => (string) __('Außenfläche'),
            self::Other => (string) __('Sonstiges'),
        };
    }
}
