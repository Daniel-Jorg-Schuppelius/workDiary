<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffTransferKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Takeoff;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Wohin die Mengen eines Aufmaßes gehen (MVP-1059); jede Art höchstens einmal je Blatt. */
enum TakeoffTransferKind: string implements HasLabel {
    use HasOptions;

    case Quote = 'quote';
    case Invoice = 'invoice';
    case Progress = 'progress';

    public function label(): string {
        return (string) __('takeoff.transfer.kind.' . $this->value);
    }

    /** Route des Ziels — ihr Modul-Gate entscheidet, ob die Übernahme angeboten wird. */
    public function targetRoute(): string {
        return match ($this) {
            self::Quote => 'quotes.show',
            self::Invoice => 'invoices.show',
            self::Progress => 'bill-of-quantities.show',
        };
    }

    public function icon(): string {
        return match ($this) {
            self::Quote => 'request_quote',
            self::Invoice => 'receipt_long',
            self::Progress => 'trending_up',
        };
    }
}
