<?php
/*
 * Created on   : Sun Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecordInvoiceMailDelivery.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Models\DocumentDispatch;
use Illuminate\Mail\Events\MessageSent;

/**
 * Zustellnachweis für Rechnungs-/Mahnmails (Feature 066 §Versand; Vollaudit
 * 2026-07, M26): Die Mailable trägt den Dispatch als Header
 * X-WorkDiary-Dispatch — beim tatsächlichen Versand wird der Status von
 * `queued` auf `sent` fortgeschrieben und die SMTP-Message-ID in `meta`
 * abgelegt. Fehlschläge setzt {@see \App\Mail\InvoiceMail::failed()}.
 */
class RecordInvoiceMailDelivery {
    public const HEADER = 'X-WorkDiary-Dispatch';

    public function handle(MessageSent $event): void {
        $header = $event->message->getHeaders()->get(self::HEADER);
        if ($header === null) {
            return;
        }

        $dispatch = DocumentDispatch::query()
            ->withoutGlobalScopes()
            ->find((int) $header->getBodyAsString());
        if ($dispatch === null) {
            return;
        }

        $dispatch->forceFill([
            'status' => 'sent',
            'meta' => [
                ...(array) $dispatch->meta,
                'message_id' => (string) $event->message->getHeaders()->get('Message-ID')?->getBodyAsString(),
                'sent_at' => now()->toIso8601String(),
            ],
        ])->save();
    }
}
