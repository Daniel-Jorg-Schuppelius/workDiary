<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BudgetReleaseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\{HasLabel, HasStatusTransitions};

/** Stand eines Budgets je Geschäftsjahr und Kostenstelle (MVP-983). */
enum BudgetReleaseStatus: string implements HasLabel, HasStatusTransitions {
    use \App\Enums\Concerns\HasTransitions;
    use HasOptions;

    case Draft = 'draft';
    case Released = 'released';

    public function label(): string {
        return (string) __('enums.finance.budget-release-status.' . $this->value);
    }

    public function tone(): string {
        return $this === self::Released ? 'success' : 'ghost';
    }

    /** Freigabe sperrt, ein Nachtrag öffnet wieder. @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Released],
            self::Released => [self::Draft],
        };
    }
}
