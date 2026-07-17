<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebPhase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * GAEB-DA-XML-Austauschphase (Feature 049, MVP-081). Der numerische Code
 * entspricht dem DA-Kürzel der Datei (z. B. X81 = Leistungsverzeichnis ohne
 * Preise). Die Ziellinie ist GAEB DA XML 3.3; höhere Phasen wie X94/X96
 * (REB/Zahlungen) sind bewusst „Später" und hier nicht gelistet.
 */
enum GaebPhase: string implements HasLabel {
    use HasOptions;

    case Lv = '81';            // Leistungsverzeichnis (ohne Preise)
    case Estimate = '82';      // Kostenanschlag
    case RequestForBid = '83'; // Angebotsaufforderung
    case Bid = '84';           // Angebotsabgabe
    case SideBid = '85';       // Nebenangebot
    case Award = '86';         // Auftragserteilung

    public function label(): string {
        return __('gaeb.phase.' . $this->value);
    }

    /** Trägt diese Phase verbindliche Einheits-/Gesamtpreise? */
    public function carriesPrices(): bool {
        return match ($this) {
            self::Lv, self::RequestForBid => false,
            default => true,
        };
    }

    /** Tolerant aus dem DP-/Phasen-Attribut der Datei ableiten (z. B. "84"). */
    public static function fromCode(?string $code): ?self {
        if ($code === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $code) ?? '';

        return self::tryFrom($digits);
    }
}
