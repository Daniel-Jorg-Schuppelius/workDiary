<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Shipping;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus eines Versandauftrags (Feature 059, MVP-128). `Draft` = angelegt,
 * noch ohne Label; `Labeled` = Label erzeugt/übergeben; `InTransit`/`Delivered`/
 * `Problem` folgen aus der Sendungsverfolgung; `Cancelled` = vor Übergabe
 * storniert.
 */
enum ShipmentStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Labeled = 'labeled';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Problem = 'problem';
    case Cancelled = 'cancelled';

    public function label(): string {
        return (string) __('shipping.status.' . $this->value);
    }

    /** Endzustand — keine weitere Statusänderung erwartet. */
    public function isTerminal(): bool {
        return $this === self::Delivered || $this === self::Cancelled;
    }

    /** Vor Übergabe an den Carrier (Storno noch möglich). */
    public function isCancellable(): bool {
        return $this === self::Draft || $this === self::Labeled;
    }
}
