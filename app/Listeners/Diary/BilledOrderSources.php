<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BilledOrderSources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Diary;

use App\Events\Finance\{BillingTransferCancelled, BillingTransferCompleted};
use App\Events\Invoicing\{InvoiceCancelled, InvoiceIssued};
use App\Models\Finance\BillingTransfer;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Material\MaterialUsage;
use App\Models\Time\TimeEntry;
use App\Support\MorphMap;
use Illuminate\Support\Facades\DB;

/** Liest aus einem Beleg-Event, welche Zeiten und welches Material der Beleg abrechnet und wie er heißt. */
trait BilledOrderSources {
    /** @return array{reference: string, time_entry_ids: list<int>, material_usage_ids: list<int>, origin: array<string, mixed>}|null */
    private function sourcesOf(InvoiceIssued|InvoiceCancelled|BillingTransferCompleted|BillingTransferCancelled $event): ?array {
        if ($event instanceof InvoiceIssued || $event instanceof InvoiceCancelled) {
            $invoice = Invoice::query()->where('organization_id', $event->organizationId)->find($event->invoiceId);
            // Storno- und Gutschriftbelege tragen die Zeiten des Originals mit, rechnen aber nichts ab.
            if ($invoice === null || $invoice->isCreditNote() || $invoice->type === Invoice::TYPE_CANCELLATION || $invoice->isProforma()) {
                return null;
            }
            $itemIds = InvoiceItem::query()->where('invoice_id', $invoice->id)->pluck('id');
            $ids = InvoiceItem::query()->whereKey($itemIds)->whereNotNull('time_entry_id')->pluck('time_entry_id')
                ->merge(DB::table('invoice_item_time_entries')->whereIn('invoice_item_id', $itemIds)->pluck('time_entry_id'));

            return [
                'reference' => (string) $invoice->number,
                'time_entry_ids' => array_values(array_unique(array_map('intval', $ids->all()))),
                'material_usage_ids' => array_values(array_map('intval', InvoiceItem::query()->whereKey($itemIds)->whereNotNull('material_usage_id')->pluck('material_usage_id')->all())),
                'origin' => ['invoice_id' => (int) $invoice->id],
            ];
        }

        $transfer = BillingTransfer::query()->where('organization_id', $event->organizationId)->with('externalReference')->find($event->billingTransferId);
        if ($transfer === null) {
            return null;
        }
        $number = (string) ($transfer->externalReference->external_number ?? '');

        return [
            'reference' => $number !== '' ? $number : (string) __('diary.lifecycle.invoiced.transfer_reference', ['id' => $transfer->id]),
            'time_entry_ids' => array_values(array_map('intval', $transfer->items()->where('source_type', MorphMap::alias(TimeEntry::class))->pluck('source_id')->all())),
            'material_usage_ids' => array_values(array_map('intval', $transfer->items()->where('source_type', MorphMap::alias(MaterialUsage::class))->pluck('source_id')->all())),
            'origin' => ['billing_transfer_id' => (int) $transfer->id],
        ];
    }
}
