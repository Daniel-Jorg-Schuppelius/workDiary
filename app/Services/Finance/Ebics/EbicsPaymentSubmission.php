<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsPaymentSubmission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Ebics;

use App\Models\Finance\{EbicsConnection, PaymentRun};
use App\Models\Platform\User;
use App\Services\Billing\Sepa\PaymentRunService;
use App\Services\Finance\Ebics\Contracts\EbicsGateway;
use App\Services\Finance\Ebics\Exceptions\EbicsException;
use Throwable;

/**
 * Zahllauf per EBICS einreichen (MVP-124): dieselbe archivierte Datei wie beim
 * Download (`PaymentRunService::export`), je Zahllauf genau einmal. Die
 * Freigabe (VEU) erteilt der Zeichnungsberechtigte bei der Bank.
 */
class EbicsPaymentSubmission {
    public function __construct(
        private readonly EbicsGateway $gateway,
        private readonly PaymentRunService $runs,
        private readonly EbicsConnectionService $connections,
    ) {}

    public function connectionFor(PaymentRun $run): ?EbicsConnection {
        $connection = EbicsConnection::query()->where('bank_account_id', $run->bank_account_id)->first();

        return $connection instanceof EbicsConnection && $connection->isActive() ? $connection : null;
    }

    public function alreadySubmitted(PaymentRun $run, EbicsConnection $connection): bool {
        return $connection->journal()->where('event', 'ebics_payment_submitted')->get()
            ->contains(static fn ($entry): bool => (int) ($entry->payloadData()['payment_run_id'] ?? 0) === (int) $run->id);
    }

    public function submit(PaymentRun $run, User $actor): string {
        $connection = $this->connectionFor($run) ?? throw new EbicsException('not_active');
        if ($this->alreadySubmitted($run, $connection)) {
            throw new EbicsException('already_submitted');
        }

        $xml = $this->runs->export($run, $actor);
        $fileName = ($run->message_id ?? ('run-' . $run->id)) . '.xml';
        try {
            $orderId = $this->gateway->uploadPayment($connection, $run->kind, $xml, $fileName);
        } catch (Throwable $e) {
            $this->connections->fail($connection, $actor, 'ebics_payment_submitted', $e);

            throw $e instanceof EbicsException ? $e : new EbicsException('failed', null, class_basename($e));
        }

        $connection->record('ebics_payment_submitted', ['payment_run_id' => $run->id, 'order_id' => $orderId, 'message_id' => $run->message_id], $actor);
        $run->audit('paymentRun.ebicsSubmitted', ['order_id' => $orderId]);

        return $orderId;
    }
}
