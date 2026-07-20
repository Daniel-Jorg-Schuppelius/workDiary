<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeOrderState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Billbee;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Dokumentierte Billbee-Bestellstatus (Feature 093 / MVP-433) — ersetzt das
 * frühere STATE_LABELS-Const-Array am Model (Vollaudit 2026-07, M53).
 * Labels laufen wie überall über lang enums.* (5 Sprachen, D1-Vertrag);
 * unbekannte Werte zeigt {@see \App\Models\BillbeeOrder::stateLabel()}
 * weiterhin als '#<int>' (tryFrom-Fallback).
 */
enum BillbeeOrderState: int implements HasLabel {
    use HasOptions;

    case Ordered = 1;
    case Confirmed = 2;
    case Paid = 3;
    case Shipped = 4;
    case Complaint = 5;
    case Deleted = 6;
    case Completed = 7;
    case Canceled = 8;
    case Archived = 9;
    case FirstReminder = 11;
    case SecondReminder = 12;
    case Packed = 13;
    case Offered = 14;
    case PaymentReminder = 15;
    case Fulfilling = 16;

    public function label(): string {
        return (string) __('enums.billbee.order_state.' . $this->value);
    }
}
