<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarrantyPeriodScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Warranty\WarrantyStatus;
use App\Models\{User, Warranty\WarrantyPeriod};
use App\Services\Notification\NotificationDispatcher;
use App\Services\Warranty\WarrantyService;
use Illuminate\Support\Carbon;

/**
 * Gewährleistungsfristen (Feature 115, MVP-604).
 *
 * Zwei Zweige mit unterschiedlicher Dringlichkeit:
 *  - `due`: Die Frist läuft in einem der konfigurierten Vorläufe ab
 *    (6/3/1 Monate). Danach ist die eigene Haftung beendet bzw. der
 *    Anspruch gegen den Subunternehmer verloren.
 *  - `overdue`: Eine SUB-Frist endet vor der eigenen — der teure Fall.
 *    Wer hier nicht rügt, haftet allein für einen fremden Mangel.
 */
class WarrantyPeriodScan extends AbstractDeadlineScan {
    public function __construct(private readonly WarrantyService $warranties) {}

    public function key(): string {
        return 'warranties';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        // 6/3/1 Monate: Die Vorläufe sind bewusst grob — eine Gewährleistung
        // läuft Jahre, eine Tages-Genauigkeit hilft niemandem.
        $windows = [180, 90, 30];
        $subcontractorFirst = $this->warranties->subcontractorsEndingFirst();

        return $this->runScan($dispatcher, [
            'affected' => fn (WarrantyPeriod $period): ?User => $period->responsible_user_id === null
                ? null
                : User::query()->find($period->responsible_user_id),
            'due' => [
                'query' => fn () => WarrantyPeriod::query()
                    ->where('status', WarrantyStatus::Open->value)
                    ->whereDate('ends_on', '>=', $today->toDateString())
                    ->whereDate('ends_on', '<=', $today->copy()->addDays(max($windows))->toDateString()),
                'event' => NotificationEvent::WarrantyExpiring,
                'payload' => fn (WarrantyPeriod $period): array => $this->warrantyPayload($period, 'warranty_expiring'),
            ],
            'overdue' => [
                'query' => fn () => WarrantyPeriod::query()
                    ->whereKey($subcontractorFirst->pluck('id')->all() ?: [0]),
                'event' => NotificationEvent::WarrantySubcontractorEndsFirst,
                'payload' => fn (WarrantyPeriod $period): array => $this->warrantyPayload($period, 'warranty_subcontractor_first'),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function warrantyPayload(WarrantyPeriod $period, string $messageKey): array {
        $params = [
            'party' => $period->partyLabel(),
            'project' => (string) ($period->project->name ?? '–'),
            'date' => $period->ends_on->format('d.m.Y'),
        ];

        return [
            'title' => (string) __('notification.message.warranty_title', ['project' => $params['project']]),
            'title_key' => 'notification.message.warranty_title',
            'title_params' => ['project' => $params['project']],
            'message' => (string) __('notification.message.' . $messageKey, $params),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => $params + ['date' => $period->ends_on->toDateString()],
            'url' => route('warranties.index'),
            'due_at' => $period->ends_on,
        ];
    }
}
