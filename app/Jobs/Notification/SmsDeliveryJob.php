<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsDeliveryJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Notification;

use App\Enums\Notification\SmsDeliveryStatus;
use App\Models\Notification\NotificationDispatchLog;
use App\Models\User;
use App\Services\Notification\Sms\SmsChannelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Zustellung einer Alarm-SMS (Feature 147, MVP-730).
 *
 * **Genau ein Versuch.** Das ist die bewusste Gegenposition zu allen anderen
 * Zustell-Jobs: ein wiederholter POST an ein SMS-Gateway ist keine
 * Wiederholung, sondern eine zweite SMS — mit zweiter Zustellung beim
 * Empfänger und zweiter Rechnungszeile. Weder seven.io noch sipgate
 * deduplizieren serverseitig (kein Idempotency-Key im API-Vertrag), also darf
 * niemand hier „sicherheitshalber" nochmal senden. Das gilt auf beiden Ebenen:
 * hier `$tries = 1`, und im HTTP-Client kein `retry_non_idempotent`
 * (api-toolkit ≥ v2.9.2 lässt ein gesendetes POST von sich aus liegen; nur
 * Fehler VOR dem Senden — DNS/Connect/TLS — wiederholt es weiterhin selbst).
 *
 * Ein Fehlschlag ist damit endgültig und steht als solcher im
 * `notification_dispatch_log`; die Alarmierung selbst ist über In-App/Mail/Push
 * ohnehin schon raus.
 */
class SmsDeliveryJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Siehe Klassendoku: eine SMS, ein Versuch. */
    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly int $dispatchLogId,
        public readonly int $recipientId,
        public readonly string $text,
    ) {}

    public function handle(SmsChannelService $channel): void {
        $log = NotificationDispatchLog::query()->withoutGlobalScopes()->find($this->dispatchLogId);
        $recipient = User::query()->find($this->recipientId);

        if (! $log instanceof NotificationDispatchLog || ! $recipient instanceof User) {
            return;
        }
        if ($log->status !== null) {
            return; // schon zugestellt/entschieden — nie ein zweites Mal
        }

        $channel->deliver($log, $recipient, $this->text);
    }

    /** Transportabbruch: der Fehlschlag gehört in den Nachweis, nicht nur ins Log. */
    public function failed(?Throwable $e): void {
        $log = NotificationDispatchLog::query()->withoutGlobalScopes()->find($this->dispatchLogId);
        if ($log instanceof NotificationDispatchLog && $log->status === null) {
            $log->forceFill([
                'status' => SmsDeliveryStatus::Failed,
                'error_code' => $e !== null ? mb_substr(class_basename($e), 0, 64) : 'unknown',
                'status_at' => Carbon::now(),
            ])->save();
        }
    }
}
