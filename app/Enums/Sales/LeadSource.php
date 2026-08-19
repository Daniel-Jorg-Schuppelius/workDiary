<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Herkunft eines Leads (Feature 091) — Auswertungsdimension, kein Freitext. */
enum LeadSource: string implements HasLabel {
    use HasOptions;

    case Referral = 'referral';
    case Web = 'web';
    case TradeFair = 'trade_fair';
    case Phone = 'phone';
    case Other = 'other';

    public function label(): string {
        return (string) __('enums.sales.lead_source.' . $this->value);
    }
}
