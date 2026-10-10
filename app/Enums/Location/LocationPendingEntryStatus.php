<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationPendingEntryStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Location;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Zeitvorschlags aus einem Geofence-Besuch: offen, als Zeitbuchung übernommen oder verworfen. */
enum LocationPendingEntryStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Open = 'open';
    case Imported = 'imported';
    case Dismissed = 'dismissed';

    public function label(): string {
        return match ($this) {
            self::Open => (string) __('enums.location.location_pending_entry_status.open'),
            self::Imported => (string) __('enums.location.location_pending_entry_status.imported'),
            self::Dismissed => (string) __('enums.location.location_pending_entry_status.dismissed'),
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Open => [self::Imported, self::Dismissed],
            self::Imported, self::Dismissed => [],
        };
    }
}
