<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenderDeadlineScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\Applications\ApplicationOpportunity;
use App\Models\User;
use App\Services\Notification\NotificationDispatcher;
use Closure;
use Illuminate\Support\Carbon;

/**
 * Vergabefristen (MVP-626). Anders als die meisten Fristen sind sie
 * **Ausschlussfristen**: Wer die Angebotsfrist verstreichen lässt, ist raus
 * — eine Erinnerung danach hilft nicht mehr, wird aber trotzdem gemeldet,
 * damit die Akte geschlossen wird.
 *
 * Die Bindefrist läuft umgekehrt: Nach ihr ist der **Bieter** frei, das
 * Angebot also nicht mehr verbindlich.
 *
 * @phpstan-import-type TNotifyPayload from AbstractDeadlineScan
 */
class TenderDeadlineScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'tenders';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($options->dueDays);

        /** @var Closure(): \Illuminate\Database\Eloquent\Builder<ApplicationOpportunity> $open */
        $open = static fn (): \Illuminate\Database\Eloquent\Builder => ApplicationOpportunity::query()
            ->whereIn('status', ApplicationOpportunity::OPEN_STATUSES)
            ->with('responsible');

        $sent = $this->runScan($dispatcher, [
            'affected' => fn (ApplicationOpportunity $tender): ?User => $tender->responsible,
            'due' => [
                'query' => fn () => $open()
                    ->whereNotNull('submission_deadline')
                    ->whereBetween('submission_deadline', [$today, $horizon]),
                'event' => NotificationEvent::TenderSubmissionDueSoon,
                'payload' => fn (ApplicationOpportunity $tender): array => $this->tenderPayload(
                    $tender,
                    'tender_submission_due_soon',
                    $tender->submission_deadline,
                ),
            ],
            'overdue' => [
                'query' => fn () => $open()
                    ->whereNotNull('submission_deadline')
                    ->where('submission_deadline', '<', $today),
                'event' => NotificationEvent::TenderSubmissionOverdue,
                'payload' => fn (ApplicationOpportunity $tender): array => $this->tenderPayload(
                    $tender,
                    'tender_submission_overdue',
                    $tender->submission_deadline,
                ),
            ],
        ]);

        // Die Bindefrist braucht einen eigenen Lauf: runScan kennt nur die
        // Phasen „fällig" und „überfällig", und eine ablaufende Bindefrist ist
        // weder das eine noch das andere - sie betrifft ein bereits
        // abgegebenes Angebot.
        $sent += $this->runScan($dispatcher, [
            'affected' => fn (ApplicationOpportunity $tender): ?User => $tender->responsible,
            'due' => [
                'query' => fn () => $open()
                    ->whereNotNull('binding_until')
                    ->whereBetween('binding_until', [$today, $horizon]),
                'event' => NotificationEvent::TenderBindingExpiring,
                'payload' => fn (ApplicationOpportunity $tender): array => $this->tenderPayload(
                    $tender,
                    'tender_binding_expiring',
                    $tender->binding_until,
                ),
            ],
        ]);

        return $sent;
    }

    /**
     * @return TNotifyPayload
     */
    private function tenderPayload(ApplicationOpportunity $tender, string $key, ?\Carbon\CarbonInterface $date): array {
        return [
            'title' => (string) $tender->title,
            'message' => (string) __('notification.message.' . $key, ['date' => $date?->format('d.m.Y') ?? '–']),
            'message_key' => 'notification.message.' . $key,
            'message_params' => ['date' => $date?->toDateString() ?? '–'],
            'url' => route('tenders.show', $tender),
            'due_at' => $date,
        ];
    }
}
