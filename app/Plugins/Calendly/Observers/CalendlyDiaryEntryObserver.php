<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyDiaryEntryObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Observers;

use App\Enums\Diary\Status;
use App\Models\{AppointmentRequest, DiaryEntry};
use App\Plugins\Calendly\Jobs\CalendlyCancelSyncJob;

/**
 * Schlanker Cancel-Sync-Trigger (Feature 095, P5; Muster BhbInvoiceObserver):
 * wird ein Dispositionseintrag storniert, der aus einem BESTÄTIGTEN
 * Calendly-Terminwunsch entstand, wird NUR ein Queue-Job enqueued — keine
 * Calendly-Logik in Model-Events. Kein Echo zurück nach Calendly: bei einer
 * Calendly-seitigen Absage setzt der Ingest den Terminwunsch VOR dem Storno
 * des Eintrags auf `canceled`/`superseded`, der Trigger findet dann keinen
 * `confirmed`-Terminwunsch mehr.
 */
class CalendlyDiaryEntryObserver {
    public function updated(DiaryEntry $entry): void {
        if (! $entry->wasChanged('status') || $entry->status !== Status::Cancelled) {
            return;
        }

        $request = AppointmentRequest::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('source', AppointmentRequest::SOURCE_CALENDLY)
            ->where('status', AppointmentRequest::STATUS_CONFIRMED)
            ->where('diary_entry_id', $entry->id)
            ->first();
        if (! $request instanceof AppointmentRequest) {
            return;
        }

        // afterCommit: der Storno läuft in einer Transaktion — der Sync darf
        // erst nach deren Commit starten (und nie bei einem Rollback).
        $reason = $entry->getAttribute('cancellation_reason');
        CalendlyCancelSyncJob::dispatch((int) $request->id, is_string($reason) ? $reason : null)->afterCommit();
    }
}
