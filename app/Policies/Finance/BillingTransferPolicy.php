<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Finance;

use App\Enums\Finance\TransferChannel;
use App\Enums\User\Permission as P;
use App\Models\Finance\BillingTransfer;
use App\Models\User;

/**
 * Policy für Übergabenachweise (Feature 045): Lesen über finance.viewAny,
 * alle schreibenden Aktionen kanal-spezifisch (finance.transfer.time bzw.
 * finance.transfer.material — getrennte Verantwortlichkeiten je Kanal).
 */
class BillingTransferPolicy {
    public function viewAny(User $user): bool {
        return $user->can(P::FinanceViewAny->value);
    }

    public function view(User $user, BillingTransfer $transfer): bool {
        return $user->can(P::FinanceViewAny->value);
    }

    /** Anlegen eines Drafts ist kanal-spezifisch. */
    public function create(User $user, TransferChannel $channel): bool {
        return $user->can($channel->permission()->value);
    }

    public function confirm(User $user, BillingTransfer $transfer): bool {
        return $this->manageChannel($user, $transfer);
    }

    public function markTransferred(User $user, BillingTransfer $transfer): bool {
        return $this->manageChannel($user, $transfer);
    }

    public function markFailed(User $user, BillingTransfer $transfer): bool {
        return $this->manageChannel($user, $transfer);
    }

    public function void(User $user, BillingTransfer $transfer): bool {
        return $this->manageChannel($user, $transfer);
    }

    private function manageChannel(User $user, BillingTransfer $transfer): bool {
        return $user->can($transfer->channel->permission()->value);
    }
}
