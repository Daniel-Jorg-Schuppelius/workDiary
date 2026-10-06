<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Enums;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Stand der orgaMAX-Verbindung (MVP-306): Absicht → Callback → ausdrückliche
 * Kontobestätigung → aktiv oder blockiert.
 */
enum OrgaMaxConnectionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    /** Spaltenvorgabe; der Verbindungsdialog schreibt sofort `pending_callback`. */
    case Draft = 'draft';
    case PendingCallback = 'pending_callback';
    case PendingConfirmation = 'pending_confirmation';
    case Active = 'active';
    case Blocked = 'blocked';
    case Disconnected = 'disconnected';

    public function label(): string {
        return (string) __('orgamax::orgamax.status.' . $this->value);
    }

    /**
     * Neu verbinden und trennen geht aus jedem Zustand; blockiert wird bei
     * fehlenden Scopes oder abgelehntem Token-Tausch.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::PendingCallback, self::Disconnected],
            self::PendingCallback => [self::PendingConfirmation, self::Blocked, self::Disconnected],
            self::PendingConfirmation => [self::PendingCallback, self::Active, self::Blocked, self::Disconnected],
            self::Active => [self::PendingCallback, self::Blocked, self::Disconnected],
            self::Blocked => [self::PendingCallback, self::Active, self::Disconnected],
            self::Disconnected => [self::PendingCallback],
        };
    }
}
