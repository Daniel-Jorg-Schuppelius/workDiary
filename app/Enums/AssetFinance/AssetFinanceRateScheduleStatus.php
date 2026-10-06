<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceRateScheduleStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

use App\Enums\Contracts\HasLabel;

/** Stand einer Ratenplan-Zeile (MVP-272/274): „bezahlt“ heißt nur „Eingangsrechnung referenziert“ (D11). */
enum AssetFinanceRateScheduleStatus: string implements HasLabel {
    case Planned = 'planned';
    case Paid = 'paid';

    /** Im Schema vorgesehen, heute von keinem Code geschrieben. */
    case Overdue = 'overdue';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
