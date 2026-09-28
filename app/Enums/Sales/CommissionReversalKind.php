<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionReversalKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Anlass einer Rückrechnungszeile (Feature 146, MVP-989). Nur die
 * Zahlungsrücknahme gibt Grundlage frei, die eine spätere Zahlung wieder
 * entstehen lässt; Gutschrift, Storno und Neuzuordnung mindern endgültig.
 * Zeilen vor MVP-989 tragen keinen Anlass.
 */
enum CommissionReversalKind: string implements HasLabel {
    use HasOptions;

    case CreditNote = 'credit_note';
    case Cancellation = 'cancellation';
    case Reassignment = 'reassignment';
    case Payment = 'payment';

    public function label(): string {
        return (string) __('commission.reversal_kind.' . $this->value);
    }
}
