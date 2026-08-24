<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuaranteeDeadlineScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{Guarantee\Guarantee, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Bürgschaften (Feature 114, MVP-603).
 *
 * Zwei gegenläufige Risiken, deshalb zwei Zweige:
 *  - **Befristung läuft ab** (`due`): Bei einer ERHALTENEN Bürgschaft ist
 *    danach die Sicherheit weg; bei einer gestellten muss sie ggf.
 *    verlängert werden.
 *  - **Rückgabe fällig** (`overdue`): Eine GESTELLTE Bürgschaft, deren
 *    abgelöster Einbehalt freigegeben ist, gehört zurückgefordert — sonst
 *    läuft die Avalprovision weiter.
 */
class GuaranteeDeadlineScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'guarantees';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        $expiringDays = $options->expiringDays;
        $active = static fn () => Guarantee::query()
            ->where('status', \App\Enums\Guarantee\GuaranteeStatus::Active->value);

        return $this->runScan($dispatcher, [
            'affected' => fn (Guarantee $guarantee): ?User => $guarantee->responsible_user_id === null
                ? null
                : User::query()->find($guarantee->responsible_user_id),
            'due' => [
                'query' => fn () => $active()
                    ->whereNotNull('expires_on')
                    ->whereDate('expires_on', '<=', $today->copy()->addDays($expiringDays)->toDateString()),
                'event' => NotificationEvent::GuaranteeExpiring,
                'payload' => fn (Guarantee $guarantee): array => $this->guaranteePayload($guarantee, 'guarantee_expiring', $guarantee->expires_on),
            ],
            'overdue' => [
                // Der abgelöste Einbehalt ist freigegeben ⇒ die Sicherung ist
                // beendet, die Urkunde gehört zurück.
                'query' => fn () => $active()
                    ->whereHas('retention', fn ($q) => $q->whereIn('status', [
                        \App\Enums\Invoicing\RetentionStatus::Released->value,
                    ])),
                'event' => NotificationEvent::GuaranteeReturnDue,
                'payload' => fn (Guarantee $guarantee): array => $this->guaranteePayload($guarantee, 'guarantee_return_due', $guarantee->expires_on),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function guaranteePayload(Guarantee $guarantee, string $messageKey, ?Carbon $dueAt): array {
        $params = [
            'reference' => (string) ($guarantee->reference ?? '–'),
            'issuer' => $guarantee->issuerLabel(),
            'amount' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($guarantee->amount->toFloat(), 2, withThousandsSeparator: true),
            'date' => $dueAt?->format('d.m.Y') ?? '–',
        ];

        return [
            'title' => (string) __('notification.message.guarantee_title', ['reference' => $params['reference']]),
            'title_key' => 'notification.message.guarantee_title',
            'title_params' => ['reference' => $params['reference']],
            'message' => (string) __('notification.message.' . $messageKey, $params),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => $params + ['date' => $dueAt?->toDateString() ?? '–'],
            'url' => route('guarantees.index'),
            'due_at' => $dueAt,
        ];
    }
}
