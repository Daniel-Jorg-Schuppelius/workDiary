<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingRetentionReleaseScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\DeadlineScans;

use App\Enums\Invoicing\RetentionStatus;
use App\Enums\Notification\NotificationEvent;
use App\Models\Finance\IncomingInvoiceRetention;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use App\Support\Query\DateRange;
use Illuminate\Support\Carbon;

/** Freigabe fälliger Einbehalte an Eingangsrechnungen (MVP-953): nahender und überschrittener Termin. */
class IncomingRetentionReleaseScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'incoming_retention_releases';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = Carbon::today();
        $open = static fn () => IncomingInvoiceRetention::query()
            ->where('status', RetentionStatus::Open->value)
            ->whereNotNull('due_on');

        return $this->runScan($dispatcher, [
            'due' => [
                'query' => fn () => $open()
                    ->where('due_on', '>=', DateRange::dayAfter($today))
                    ->where('due_on', '<', DateRange::dayAfter($today->copy()->addDays($options->expiringDays))),
                'event' => NotificationEvent::RetentionReleaseDue,
                'payload' => fn (IncomingInvoiceRetention $retention): array => $this->payload($retention, 'due'),
            ],
            'overdue' => [
                'query' => fn () => $open()->where('due_on', '<', DateRange::dayAfter($today)),
                'event' => NotificationEvent::RetentionReleaseDue,
                'payload' => fn (IncomingInvoiceRetention $retention): array => $this->payload($retention, 'overdue'),
            ],
        ]);
    }

    /** @return array{title: string, message: string, url: string|null, due_at: \Illuminate\Support\Carbon|null} */
    private function payload(IncomingInvoiceRetention $retention, string $phase): array {
        $invoice = $retention->incomingEInvoice;
        $params = [
            'supplier' => (string) ($invoice->seller_name ?? '–'),
            'number' => (string) ($invoice->invoice_number ?? '–'),
            'date' => $retention->due_on?->format('d.m.Y') ?? '–',
        ];

        return [
            'title' => (string) __('sepa.retention.notification.title', $params),
            'title_key' => 'sepa.retention.notification.title',
            'title_params' => $params,
            'message' => (string) __('sepa.retention.notification.' . $phase, $params),
            'message_key' => 'sepa.retention.notification.' . $phase,
            'message_params' => ['date' => $retention->due_on?->toDateString() ?? '–'] + $params,
            'url' => $invoice?->document !== null ? route('finance.incoming-invoices.show', $invoice->document) : null,
            'due_at' => $retention->due_on,
        ];
    }
}
