<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallNoticeMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Listeners\RecordInvoiceMailDelivery;
use App\Mail\Concerns\TracksDocumentDispatch;
use App\Models\Customer\Customer;
use App\Models\Inventory\{Recall, RecallItem};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Rückruf-Anschreiben an einen Kunden (MVP-922): Nachricht der Aktion samt
 * betroffener Seriennummern; Zustellnachweis über die Kopfzeile
 * (RecordInvoiceMailDelivery → sent), Queue-Fehlschlag → failed.
 */
class RecallNoticeMail extends Mailable implements ShouldQueue {
    use Queueable;
    use SerializesModels;
    use TracksDocumentDispatch;

    public function __construct(
        public readonly int $recallId,
        public readonly int $customerId,
        public readonly int $dispatchId,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('recall.mail.subject', ['title' => $this->recall()->title, 'number' => (string) $this->recall()->number]));
    }

    public function content(): Content {
        $recall = $this->recall();
        $customer = Customer::query()->withoutGlobalScopes()->findOrFail($this->customerId);
        $serials = RecallItem::query()->withoutGlobalScopes()
            ->where('recall_id', $recall->id)
            ->where('customer_id', $customer->id)
            ->with('serial')
            ->get()
            ->map(static fn (RecallItem $item): ?string => $item->serial?->serial_no)
            ->filter()
            ->implode(', ');
        $product = trim(($recall->variant->article->name ?? '') . ($recall->variant?->sku ? ' (' . $recall->variant->sku . ')' : ''));
        $text = (string) __('recall.mail.body', [
            'name' => $customer->name,
            'product' => $product,
            'message' => $recall->customer_message ?? (string) __('recall.mail.default_message'),
        ]);
        if ($serials !== '') {
            $text .= "\n\n" . __('recall.mail.serials', ['serials' => $serials]);
        }

        return new Content(view: 'mail.document', text: 'mail.document-text', with: ['html' => nl2br(e($text)), 'text' => $text]);
    }

    private function recall(): Recall {
        return Recall::query()->withoutGlobalScopes()->with('variant.article')->findOrFail($this->recallId);
    }
}
