<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionReleaseScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Invoicing\RetentionStatus;
use App\Enums\Notification\NotificationEvent;
use App\Models\Invoicing\InvoiceRetention;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Support\Carbon;

/**
 * Freigabe fälliger Sicherheitseinbehalte (Feature 113, MVP-602).
 *
 * Der Einbehalt verjährt zugunsten des Kunden: Wer ihn nach Ablauf der
 * Gewährleistung nicht einfordert, verliert ihn. Deshalb wird BEIDES
 * gemeldet — der nahende Termin und der bereits überschrittene.
 */
class RetentionReleaseScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'retention_releases';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        $expiringDays = $options->expiringDays;
        $open = static fn () => InvoiceRetention::query()
            ->where('status', RetentionStatus::Open->value)
            ->whereNotNull('due_on');

        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn () => $open()
                    ->whereDate('due_on', '>', $today->toDateString())
                    ->whereDate('due_on', '<=', $today->copy()->addDays($expiringDays)->toDateString()),
                'event' => NotificationEvent::RetentionReleaseDue,
                'payload' => fn (InvoiceRetention $retention): array => $this->retentionPayload($retention, 'retention_release_due'),
            ],
            'overdue' => [
                'query' => fn () => $open()->whereDate('due_on', '<=', $today->toDateString()),
                'event' => NotificationEvent::RetentionReleaseDue,
                'payload' => fn (InvoiceRetention $retention): array => $this->retentionPayload($retention, 'retention_release_overdue'),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function retentionPayload(InvoiceRetention $retention, string $messageKey): array {
        $invoice = $retention->invoice;
        $params = [
            'number' => (string) ($invoice->number ?? '–'),
            'amount' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($retention->amount->toFloat(), 2, withThousandsSeparator: true),
            'date' => $retention->due_on?->format('d.m.Y') ?? '–',
        ];

        return [
            'title' => (string) __('notification.message.retention_title', ['number' => $params['number']]),
            'title_key' => 'notification.message.retention_title',
            'title_params' => ['number' => $params['number']],
            'message' => (string) __('notification.message.' . $messageKey, $params),
            'message_key' => 'notification.message.' . $messageKey,
            'message_params' => $params + ['date' => $retention->due_on?->toDateString() ?? '–'],
            'url' => $invoice === null ? null : route('invoices.show', $invoice),
            'due_at' => $retention->due_on,
        ];
    }
}
