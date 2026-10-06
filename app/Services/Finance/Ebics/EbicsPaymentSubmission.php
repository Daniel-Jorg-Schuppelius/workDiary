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
use App\Models\Journal\JournalEntry;
use App\Models\Platform\User;
use App\Services\Billing\Sepa\PaymentRunService;
use App\Services\Finance\Ebics\Contracts\EbicsGateway;
use App\Services\Finance\Ebics\Exceptions\EbicsException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Zahllauf per EBICS einreichen (MVP-124): dieselbe archivierte Datei wie beim
 * Download (`PaymentRunService::export`), je Zahllauf genau einmal. Die
 * Freigabe (VEU) erteilt der Zeichnungsberechtigte bei der Bank.
 */
class EbicsPaymentSubmission {
    public const EVENT_STARTED = 'ebics_payment_started';

    public const EVENT_SUBMITTED = 'ebics_payment_submitted';

    public const EVENT_ABORTED = 'ebics_payment_aborted';

    public const STATE_SUBMITTED = 'submitted';

    /** Übermittlung begonnen, aber weder Auftragsnummer noch ausdrückliche Ablehnung erhalten. */
    public const STATE_UNCLEAR = 'unclear';

    public function __construct(
        private readonly EbicsGateway $gateway,
        private readonly PaymentRunService $runs,
        private readonly EbicsConnectionService $connections,
    ) {}

    public function connectionFor(PaymentRun $run): ?EbicsConnection {
        $connection = EbicsConnection::query()->where('bank_account_id', $run->bank_account_id)->first();

        return $connection instanceof EbicsConnection && $connection->isActive() ? $connection : null;
    }

    /**
     * Letzter Stand der Einreichung dieses Zahllaufs: eingereicht, unklar oder
     * null (nie begonnen bzw. nachweislich nicht erfolgt).
     */
    public function state(PaymentRun $run, EbicsConnection $connection): ?string {
        return match ($this->lastEntry($run, $connection)?->eventKey()) {
            self::EVENT_SUBMITTED => self::STATE_SUBMITTED,
            self::EVENT_STARTED => self::STATE_UNCLEAR,
            default => null,
        };
    }

    public function lastEntry(PaymentRun $run, EbicsConnection $connection): ?JournalEntry {
        return $connection->journal()
            ->whereIn('event', [self::EVENT_STARTED, self::EVENT_SUBMITTED, self::EVENT_ABORTED])
            ->get()
            ->filter(static fn (JournalEntry $entry): bool => (int) ($entry->payloadData()['payment_run_id'] ?? 0) === (int) $run->id)
            ->sortByDesc(static fn (JournalEntry $entry): int => (int) $entry->getKey())
            ->first();
    }

    public function submit(PaymentRun $run, User $actor): string {
        $connection = $this->connectionFor($run) ?? throw new EbicsException('not_active');

        // Vor dem Netzaufruf unter Sperre vormerken: zwei gleichzeitige Aufrufe oder ein Fehler hinter einem
        // angenommenen Upload reichten dieselbe Datei sonst zweimal ein (Sicherheitsaudit 2026-10-04, pub-5).
        DB::transaction(function () use ($run, $connection, $actor): void {
            PaymentRun::query()->whereKey($run->id)->lockForUpdate()->first();
            match ($this->state($run, $connection)) {
                self::STATE_SUBMITTED => throw new EbicsException('already_submitted'),
                self::STATE_UNCLEAR => throw new EbicsException('outcome_unclear'),
                default => null,
            };
            $connection->record(self::EVENT_STARTED, ['payment_run_id' => $run->id], $actor);
        });

        try {
            $xml = $this->runs->export($run, $actor);
        } catch (Throwable $e) {
            // Die Datei ist nie zur Bank gegangen.
            $this->abort($run, $connection, $actor, 'export_failed');

            throw $e;
        }

        $fileName = ($run->message_id ?? ('run-' . $run->id)) . '.xml';
        try {
            $orderId = $this->gateway->uploadPayment($connection, $run->kind, $xml, $fileName);
        } catch (Throwable $e) {
            $this->connections->fail($connection, $actor, self::EVENT_SUBMITTED, $e);
            // Nur die ausdrückliche Ablehnung der Bank heißt „nicht eingereicht“; bei jedem anderen Fehler
            // (Zeitüberschreitung, Abbruch nach dem Upload) bleibt der Ausgang offen.
            if ($e instanceof EbicsException && $e->reason === 'bank_rejected') {
                $this->abort($run, $connection, $actor, 'bank_rejected');
            }

            throw $e instanceof EbicsException ? $e : new EbicsException('failed', null, class_basename($e));
        }

        $connection->record(self::EVENT_SUBMITTED, ['payment_run_id' => $run->id, 'order_id' => $orderId, 'message_id' => $run->message_id], $actor);
        $run->audit('paymentRun.ebicsSubmitted', ['order_id' => $orderId]);

        return $orderId;
    }

    /** Nach Prüfung bei der Bank: der Auftrag liegt dort nicht vor — der Zahllauf darf erneut eingereicht werden. */
    public function confirmNotSubmitted(PaymentRun $run, User $actor): void {
        $connection = $this->connectionFor($run) ?? throw new EbicsException('not_active');
        if ($this->state($run, $connection) !== self::STATE_UNCLEAR) {
            throw new EbicsException('invalid_step');
        }
        $this->abort($run, $connection, $actor, 'confirmed_by_user');
    }

    private function abort(PaymentRun $run, EbicsConnection $connection, User $actor, string $reason): void {
        $connection->record(self::EVENT_ABORTED, ['payment_run_id' => $run->id, 'reason' => $reason], $actor);
    }
}
