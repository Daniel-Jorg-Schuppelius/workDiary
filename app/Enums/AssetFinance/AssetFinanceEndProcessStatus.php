<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceEndProcessStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\AssetFinance;

use App\Enums\Contracts\HasLabel;

/** Stand eines Rückgabe-/Ende-Prozesses (MVP-276). */
enum AssetFinanceEndProcessStatus: string implements HasLabel {
    /** Spaltenvorgabe; der Code legt den Prozess gleich „in Bearbeitung“ an. */
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string {
        return (string) __('values.' . $this->value);
    }
}
