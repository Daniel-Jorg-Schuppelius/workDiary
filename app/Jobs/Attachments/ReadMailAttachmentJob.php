<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReadMailAttachmentJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Attachments;

use App\Models\Attachments\Attachment;
use App\Services\Attachments\Mail\MailAttachmentContents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Throwable;

/**
 * Liest eine abgelegte Mail-Datei einmal vollständig (Feature 204, MVP-1652):
 * Suchtext, Zusammenfassung, extrahierte Anhänge und bereinigte
 * Originaldarstellung ({@see MailAttachmentContents::extract()}).
 *
 * In der Medien-Warteschlange mit kurzem Zeitlimit und **einem** Versuch:
 * was an der Datei scheitert, scheitert beim zweiten Mal genauso. Ein
 * Abbruch (Zeitlimit, Absturz) landet über {@see failed()} als „abgebrochen“
 * in der Zeile — nichts liest dieselbe Datei in einer Schleife erneut.
 */
class ReadMailAttachmentJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $attachmentId) {
        $this->onQueue('media');

        // Wie ExtractDocumentTextJob: retry_after gilt pro Verbindung.
        if (config('queue.default') !== 'sync') {
            $this->onConnection('media');
        }
    }

    public function handle(MailAttachmentContents $contents): void {
        // Ohne Organisations-Scope: der Job läuft ohne Anmeldung, die ID kommt nur vom Anlegen.
        $attachment = Attachment::query()->withoutGlobalScopes()->find($this->attachmentId);
        if ($attachment === null || $attachment->mailFormat() === null) {
            return;
        }

        $contents->extract($attachment);
    }

    public function failed(?Throwable $exception): void {
        app(MailAttachmentContents::class)->markAborted($this->attachmentId);
    }
}
