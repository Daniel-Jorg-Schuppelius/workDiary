<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqChangeOrderPhase.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Schritt eines Nachtragsvorgangs (GAEB `COPhase`). Nachträge laufen über
 * dieselben Dateiphasen wie der Hauptauftrag — erst diese Angabe unterscheidet
 * eine Nachtragsaufforderung von einer regulären Ausschreibung.
 */
enum BoqChangeOrderPhase: string implements HasLabel {
    use HasOptions;

    case Call = 'CallChangOrder';        // Auftraggeber fordert ein Nachtragsangebot an
    case SupplementaryBid = 'SupplBid';  // Auftragnehmer bietet an
    case Agreement = 'SupplAgree';       // Vereinbarung

    public function label(): string {
        return __('gaeb.change_order.phase.' . $this->value);
    }

    /** Trägt dieser Schritt bereits Preise? */
    public function carriesPrices(): bool {
        return $this !== self::Call;
    }
}
