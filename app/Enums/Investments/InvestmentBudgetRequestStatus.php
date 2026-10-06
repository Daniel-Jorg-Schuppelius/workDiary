<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentBudgetRequestStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Investments;

use App\Enums\Concerns\HasTransitions;
use App\Enums\Contracts\HasStatusTransitions;

/** Stand eines Budgetantrags (MVP-202/203): genehmigte Stände werden nie überschrieben, ein Nachtrag ersetzt sie. */
enum InvestmentBudgetRequestStatus: string implements HasStatusTransitions {
    use HasTransitions;

    /** Spaltenvorgabe; der Code legt Anträge gleich „in Freigabe“ an. */
    case Draft = 'draft';
    case InApproval = 'in_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Superseded = 'superseded';

    /**
     * Noch nicht entschiedene Anträge.
     *
     * @return list<self>
     */
    public static function open(): array {
        return [self::Draft, self::InApproval];
    }

    public function label(): string {
        return (string) __('values.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::InApproval => [self::Approved, self::Rejected],
            self::Approved => [self::Superseded],
            self::Draft, self::Rejected, self::Superseded => [],
        };
    }
}
