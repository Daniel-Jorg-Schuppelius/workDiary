<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RidePriceKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Passenger;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Preisart einer Fahrt (MVP-456, Konzept §8). Der geplante Wert wird vor
 * Fahrtbeginn eingefroren; der tatsächliche Taxameter-/Providerwert bleibt
 * davon getrennt (Abweichungen bleiben sichtbar).
 */
enum RidePriceKind: string implements HasLabel {
    use HasOptions;

    /** Behördlicher Taxentarif (Grund-/Km-/Zeitanteil, § 51 PBefG). */
    case Tariff = 'tariff';

    /** Vorab vereinbarter Festpreis (Korridorregeln der Behörde beachten). */
    case FixedPrice = 'fixed_price';

    /** Rahmenvereinbarung/Vertragspreis (z. B. Krankenkasse, Firmenkunde). */
    case Contract = 'contract';

    public function label(): string {
        return (string) __('enums.passenger.price_kind.' . $this->value);
    }
}
