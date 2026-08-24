<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteFollowUpScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{Quote, User};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Angebots-Nachfassen (Feature 112, MVP-601). Zwei getrennte Fälle:
 *
 *  - `due`: Der gesetzte Nachfasstermin ist erreicht.
 *  - `overdue`: Das Angebot LÄUFT AB und es kam keine Reaktion. Das ist der
 *    teurere Fall — ein ausgelaufenes Angebot muss neu erstellt oder
 *    verlängert werden, und der Kunde hat womöglich nur eine Rückfrage
 *    gehabt, die er nie gestellt hat.
 *
 * Beide nur für versandte/freigegebene Angebote: Vor dem Versand gibt es
 * nichts nachzufassen.
 */
class QuoteFollowUpScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'quote_follow_ups';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $now = Carbon::now();
        $expiringDays = $options->expiringDays;
        $open = static fn () => Quote::query()->whereIn('status', ['approved', 'sent']);

        return $this->runScan($dispatcher, [
            'affected' => fn (Quote $quote): ?User => $quote->follow_up_user_id === null
                ? null
                : User::query()->find($quote->follow_up_user_id),
            'due' => [
                'query' => fn () => $open()
                    ->whereNotNull('follow_up_at')
                    ->whereNull('followed_up_at')
                    ->whereDate('follow_up_at', '<=', $now->toDateString()),
                'event' => NotificationEvent::QuoteFollowUpDue,
                'payload' => fn (Quote $quote): array => $this->quotePayload($quote, 'quote_follow_up_due', $quote->follow_up_at),
            ],
            'overdue' => [
                'query' => fn () => $open()
                    ->whereNotNull('valid_until')
                    ->whereDate('valid_until', '>=', $now->toDateString())
                    ->whereDate('valid_until', '<=', $now->copy()->addDays($expiringDays)->toDateString()),
                'event' => NotificationEvent::QuoteExpiringWithoutReaction,
                'payload' => fn (Quote $quote): array => $this->quotePayload($quote, 'quote_expiring_without_reaction', $quote->valid_until),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function quotePayload(Quote $quote, string $messageKey, ?Carbon $dueAt): array {
        $params = [
            'number' => (string) $quote->number,
            'customer' => (string) ($quote->customer?->displayLabel() ?? '–'),
            'date' => $dueAt?->format('d.m.Y') ?? '–',
        ];

        return [
            'title' => (string) __('notification.message.quote_follow_up_title', ['number' => $params['number']]),
            'title_key' => 'notification.message.quote_follow_up_title',
            'title_params' => ['number' => $params['number']],
            'message' => (string) __('notification.message.' . $messageKey, $params),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => $params + ['date' => $dueAt?->toDateString() ?? '–'],
            'url' => route('quotes.show', $quote),
            'due_at' => $dueAt,
        ];
    }
}
