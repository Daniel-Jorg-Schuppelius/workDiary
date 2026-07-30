<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RideOrderChannel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Passenger;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bestellkanal einer Fahrt (MVP-456). Für Mietwagen/Bedarfsverkehr ist der
 * Auftragseingang am Betriebssitz nachzuweisen (§ 49 Abs. 4 PBefG) —
 * `hail` (Winkkunde/Halteplatz) ist dort deshalb unzulässig.
 */
enum RideOrderChannel: string implements HasLabel {
    use HasOptions;

    case Hail = 'hail';           // Winkkunde / Taxenstand
    case Phone = 'phone';
    case App = 'app';
    case Web = 'web';
    case Mediator = 'mediator';   // Vermittlungszentrale / Plattform
    case Contract = 'contract';   // Rahmenvertrag, Serienfahrt

    public function label(): string {
        return (string) __('enums.passenger.order_channel.' . $this->value);
    }

    /** Zulässiger Eingangskanal am Betriebssitz (Nachweis führbar). */
    public function isOfficeReceipt(): bool {
        return $this !== self::Hail;
    }
}
