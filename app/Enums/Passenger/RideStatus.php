<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RideStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Passenger;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer Fahrtakte (MVP-456, Konzept §4 „Status"):
 *
 * `requested → accepted → assigned → en_route_pickup → waiting → occupied →
 * completed`
 *
 * Nebenpfade: Storno bis `assigned`, `no_show` aus `waiting`, Abbruch ab
 * `en_route_pickup`. Rückwärtsschritte gibt es nicht — Korrekturen laufen
 * über einen neuen Fahrtauftrag (auditiert).
 */
enum RideStatus: string implements HasLabel {
    use HasOptions;

    case Requested = 'requested';
    case Accepted = 'accepted';
    case Assigned = 'assigned';
    case EnRoutePickup = 'en_route_pickup';
    case Waiting = 'waiting';
    case Occupied = 'occupied';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Aborted = 'aborted';

    public function label(): string {
        return (string) __('enums.passenger.ride_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Completed => 'success',
            self::Cancelled, self::NoShow, self::Aborted => 'error',
            self::Occupied, self::EnRoutePickup => 'info',
            self::Waiting => 'warning',
            default => 'neutral',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Requested => [self::Accepted, self::Cancelled],
            self::Accepted => [self::Assigned, self::Cancelled],
            self::Assigned => [self::EnRoutePickup, self::Cancelled],
            self::EnRoutePickup => [self::Waiting, self::Occupied, self::Aborted],
            self::Waiting => [self::Occupied, self::NoShow, self::Aborted],
            self::Occupied => [self::Completed, self::Aborted],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Endzustand — keine weiteren Übergänge, Snapshots sind eingefroren. */
    public function isFinal(): bool {
        return $this->allowedTransitions() === [];
    }

    /** Fahrt läuft (Fahrer/Fahrzeug gebunden, Gerätebezug relevant). */
    public function isActive(): bool {
        return in_array($this, [self::EnRoutePickup, self::Waiting, self::Occupied], true);
    }
}
