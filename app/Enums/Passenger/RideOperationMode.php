<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RideOperationMode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Passenger;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Betriebsart einer Personenbeförderung (MVP-456). Steuert Pflichtgates,
 * Tarif-/Preisregeln und Rückkehrlogik; wird bei Annahme eingefroren — ein
 * stiller Wechsel ist ausgeschlossen (Konzept §3).
 */
enum RideOperationMode: string implements HasLabel {
    use HasOptions;

    /** Taxenverkehr nach § 47 PBefG: Pflichtfahrgebiet, behördlicher Tarif. */
    case Taxi = 'taxi';

    /** Mietwagenverkehr nach § 49 Abs. 4 PBefG: Eingangsnachweis + Rückkehrpflicht. */
    case RentalCar = 'rental_car';

    /** Gebündelter Bedarfsverkehr nach § 50 PBefG — nur wenn lizenziert. */
    case PooledOnDemand = 'pooled_on_demand';

    public function label(): string {
        return (string) __('enums.passenger.operation_mode.' . $this->value);
    }

    /** Behördlicher Pflichttarif (Taxe) statt freier Preisvereinbarung. */
    public function requiresRegulatedTariff(): bool {
        return $this === self::Taxi;
    }

    /**
     * Mietwagen/Bedarfsverkehr: Auftragseingang am Betriebssitz ist
     * nachzuweisen und aufzubewahren (§ 49 Abs. 4 PBefG).
     */
    public function requiresOrderReceipt(): bool {
        return $this !== self::Taxi;
    }

    /** Rückkehrpflicht zum Betriebssitz ohne Folgeauftrag (§ 49 Abs. 4). */
    public function requiresReturnToBase(): bool {
        return $this === self::RentalCar;
    }

    /** Erforderliche Konzessionsart (permit_type-Klassifikationscode). */
    public function permitType(): string {
        return match ($this) {
            self::Taxi => 'taxikonzession',
            self::RentalCar => 'mietwagengenehmigung',
            self::PooledOnDemand => 'bedarfsverkehrsgenehmigung',
        };
    }
}
