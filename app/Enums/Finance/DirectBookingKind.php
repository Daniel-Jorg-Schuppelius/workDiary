<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DirectBookingKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Models\Accounting\AccountingEntry;

/**
 * Fachvorgang, der seine Buchung selbst anlegt statt über einen Inbox-Vorschlag
 * (Phase 137, E9/E23). Steht im Snapshot der Buchung (`direct_booking`); bei aktivem
 * Vier-Augen-Prinzip wartet die Buchung als Entwurf in der Buchungs-Inbox.
 */
enum DirectBookingKind: string implements HasLabel {
    use HasOptions;

    /** Skonto oder Ausbuchung eines offenen Postens. */
    case OpenItemSettlement = 'open_item_settlement';

    case Clearing = 'clearing';
    case InternalTransfer = 'internal_transfer';
    case OpeningBalance = 'opening_balance';
    case VatSpecialPrepayment = 'vat_special_prepayment';

    /** Storno von Hand (E23); das automatische Storno bucht sofort. */
    case Reversal = 'reversal';

    public const SNAPSHOT_KEY = 'direct_booking';

    public static function of(AccountingEntry $entry): ?self {
        $snapshot = is_array($entry->snapshot) ? $entry->snapshot : [];

        return self::tryFrom((string) ($snapshot[self::SNAPSHOT_KEY] ?? ''));
    }

    public function label(): string {
        return (string) __('enums.finance.direct-booking-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::OpenItemSettlement => 'warning',
            self::Clearing => 'error',
            self::InternalTransfer => 'primary',
            self::OpeningBalance => 'neutral',
            self::VatSpecialPrepayment => 'accent',
            self::Reversal => 'warning',
        };
    }
}
