<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseSubmittedTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Expense\Automation;

use App\Automation\Triggers\RuleTrigger;

final class ExpenseSubmittedTrigger implements RuleTrigger {
    public const KEY = 'expense.submitted';

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('automation.trigger.expense_submitted');
    }

    /** @return array<string, mixed> */
    public function exampleConditions(): array {
        return ['all' => [['field' => 'amount_gross', 'op' => '<=', 'value' => 50]]];
    }
}
