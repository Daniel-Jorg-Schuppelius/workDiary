<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResourceKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Club;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art einer Sportstätte/Ressource (Feature 159, MVP-853): Halle, Teilfläche, Platz, Tisch, Bahn, Stand, Boot, Gerät … */
enum ClubResourceKind: string implements HasLabel {
    use HasOptions;

    case Hall = 'hall';
    case Part = 'part';
    case Pitch = 'pitch';
    case Court = 'court';
    case Table = 'table';
    case Lane = 'lane';
    case Stand = 'stand';
    case Boat = 'boat';
    case Equipment = 'equipment';
    case Horse = 'horse';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.club.resource-kind.' . $this->value);
    }

    public function icon(): string {
        return match ($this) {
            self::Hall => 'stadium',
            self::Part => 'view_column',
            self::Pitch => 'sports_soccer',
            self::Court => 'sports_tennis',
            self::Table => 'table_restaurant',
            self::Lane => 'pool',
            self::Stand => 'gps_fixed',
            self::Boat => 'rowing',
            self::Equipment => 'handyman',
            self::Horse => 'bedroom_baby',
            self::Other => 'category',
        };
    }
}
