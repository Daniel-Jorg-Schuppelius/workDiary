<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TracksDocumentDispatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Enums\Document\DocumentDispatchStatus;
use App\Listeners\RecordInvoiceMailDelivery;
use App\Models\Document\DocumentDispatch;
use Illuminate\Mail\Mailables\Headers;
use Throwable;

/**
 * Zustellnachweis einer Beleg-Mail (Vollaudit 2026-07, M26): die Kopfzeile
 * trägt die Referenz auf den {@see DocumentDispatch}, ein Queue-Fehlschlag
 * setzt ihn auf „failed“. Stand in fünf Mailables kopiert
 * (Konsolidierungs-Audit 2026-10, k3-8).
 *
 * Die Mailable führt `$dispatchId` (`int` oder `?int`); ohne Referenz
 * geschieht nichts.
 */
trait TracksDocumentDispatch {
    /** Dispatch-Referenz für {@see RecordInvoiceMailDelivery}. */
    public function headers(): Headers {
        return new Headers(text: array_filter([RecordInvoiceMailDelivery::HEADER => (string) $this->dispatchId]));
    }

    public function failed(Throwable $exception): void {
        if (! $this->dispatchId) {
            return;
        }
        $dispatch = DocumentDispatch::query()->withoutGlobalScopes()->find($this->dispatchId);
        $dispatch?->forceFill([
            'status' => DocumentDispatchStatus::Failed,
            'meta' => [...(array) $dispatch->meta, 'error' => mb_substr($exception->getMessage(), 0, 500)],
        ])->save();
    }
}
