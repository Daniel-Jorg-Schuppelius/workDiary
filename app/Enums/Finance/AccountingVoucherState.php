<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

/**
 * Anbieterneutraler Zustand eines gespiegelten Buchhaltungsbelegs (Feature 122,
 * MVP-731). Die Puller übersetzen den Anbieterwert; der Rohwert bleibt in
 * `voucher_status` stehen.
 */
enum AccountingVoucherState: string {
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
