<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance;

use App\Enums\Finance\TransferStatus;
use App\Models\Finance\BillingTransfer;

/**
 * Fachliche Verletzung der Übergabenachweis-Statusmaschine (Feature 045) —
 * gleiches Muster wie TimeCorrectionWorkflowException.
 */
class BillingTransferException extends \RuntimeException {
    /** @param  array<string, mixed>  $context */
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function illegalTransition(BillingTransfer $transfer, TransferStatus $to): self {
        return new self(
            'illegalTransition',
            (string) __('finance.error.illegal_transition', [
                'from' => $transfer->status->label(),
                'to' => $to->label(),
            ]),
            ['from' => $transfer->status->value, 'to' => $to->value, 'transfer_id' => $transfer->id],
        );
    }
}
