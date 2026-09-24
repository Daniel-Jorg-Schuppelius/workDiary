<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeAllocationHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club\Finance;

use App\Models\Club\ClubFeeClaim;
use App\Models\Finance\{BankTransaction, PaymentAllocation};
use App\Services\Club\ClubFeePaymentService;
use App\Services\Finance\Contracts\AllocationTargetHandler;
use Illuminate\Database\Eloquent\Model;

/** Beitragsforderungen (Feature 159, MVP-851) als Ziel der Bankzuordnung. */
final class ClubFeeAllocationHandler implements AllocationTargetHandler {
    public function __construct(private readonly ClubFeePaymentService $payments) {}

    public function supports(Model $target): bool {
        return $target instanceof ClubFeeClaim;
    }

    public function book(Model $target, BankTransaction $transaction, PaymentAllocation $allocation): void {
        if ($target instanceof ClubFeeClaim) {
            $this->payments->bookBankAllocation($target, $transaction, $allocation);
        }
    }

    public function revert(PaymentAllocation $allocation): void {
        $this->payments->revertBankAllocation($allocation);
    }

    public function chargeback(PaymentAllocation $original, BankTransaction $returnTransaction, PaymentAllocation $compensation, ?string $reason): void {
        $this->payments->chargebackFromBank($original, $returnTransaction, $compensation, $reason);
    }
}
