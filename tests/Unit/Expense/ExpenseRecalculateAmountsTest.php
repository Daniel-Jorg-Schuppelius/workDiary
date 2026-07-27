<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseRecalculateAmountsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Expense;

use App\Models\Expense;
use PHPUnit\Framework\TestCase;

class ExpenseRecalculateAmountsTest extends TestCase {
    public function test_calculates_tax_and_gross_from_net(): void {
        $expense = new Expense();
        $expense->amount_net = '100.00';
        $expense->tax_rate = '19.00';
        $expense->amount_gross = '0.00';

        $expense->recalculateAmounts();

        $this->assertEquals(19.00, $expense->tax_amount?->toFloat());
        $this->assertEquals(119.00, $expense->amount_gross?->toFloat());
    }

    public function test_derives_net_from_gross_when_net_is_zero(): void {
        $expense = new Expense();
        $expense->amount_net = '0';
        $expense->tax_rate = '19.00';
        $expense->amount_gross = '119.00';

        $expense->recalculateAmounts();

        $this->assertEqualsWithDelta(100.0, $expense->amount_net?->toFloat(), 0.01);
        $this->assertEqualsWithDelta(19.0, $expense->tax_amount?->toFloat(), 0.01);
        $this->assertEqualsWithDelta(119.0, $expense->amount_gross?->toFloat(), 0.01);
    }

    public function test_zero_tax_rate_leaves_net_equal_to_gross(): void {
        $expense = new Expense();
        $expense->amount_net = '50.00';
        $expense->tax_rate = '0.00';
        $expense->amount_gross = '0';

        $expense->recalculateAmounts();

        $this->assertEquals(0.0, $expense->tax_amount?->toFloat());
        $this->assertEquals(50.0, $expense->amount_gross?->toFloat());
    }

    public function test_seven_percent_reduced_rate(): void {
        $expense = new Expense();
        $expense->amount_net = '200.00';
        $expense->tax_rate = '7.00';
        $expense->amount_gross = '0';

        $expense->recalculateAmounts();

        $this->assertEquals(14.00, $expense->tax_amount?->toFloat());
        $this->assertEquals(214.00, $expense->amount_gross?->toFloat());
    }
}
