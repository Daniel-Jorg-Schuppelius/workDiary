<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqChangeOrderStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Gaeb;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Nachtragsstatus einer LV-Position (Feature 108, MVP-574). Werte und Reihenfolge
 * folgen GAEB `COStatus`; der Status an der Position hat Vorrang vor dem Status
 * des Nachtragsauftrags.
 */
enum BoqChangeOrderStatus: string implements HasLabel {
    use HasOptions;

    case Recognised = 'Recog';
    case Filed = 'Filed';
    case Offered = 'Offered';
    case Withdrawn = 'Withdrawn';
    case Rejected = 'Rejected';
    case ObjectedToRejection = 'ObjToRecj';
    case FormallyAcknowledged = 'FormAckn';
    case Approved = 'Approved';

    public function label(): string {
        return __('gaeb.item.change_order_status.' . $this->value);
    }

    /** Abgeschlossen — weder offen noch verhandelbar. */
    public function isFinal(): bool {
        return match ($this) {
            self::Withdrawn, self::Rejected, self::Approved => true,
            default => false,
        };
    }

    /** Zählt die Position mit diesem Status in der Auftragssumme mit? */
    public function countsTowardsOrder(): bool {
        return match ($this) {
            self::Approved, self::FormallyAcknowledged => true,
            default => false,
        };
    }
}
