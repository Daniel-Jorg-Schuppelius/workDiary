<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportDeliveryMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\TimeExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Automatische Lieferung eines Zeit-Exports per E-Mail (A21 · MVP-019).
 *
 * Versendet die unveränderte Export-Datei (exakt die Bytes aus dem Storage —
 * GoBD: der Anhang entspricht bitgenau dem gespeicherten, gehashten Paket)
 * an die im {@see \App\Models\TimeExportDeliveryConfig} hinterlegten
 * Empfänger. Wird synchron aus {@see \App\Jobs\DeliverTimeExportJob}
 * verschickt (kein eigenes ShouldQueue — Retry/Idempotenz steuert der Job).
 */
class TimeExportDeliveryMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(
        public TimeExport $export,
        private readonly string $fileBytes,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: __('wage_types.mail.subject', [
            'profile' => $this->export->profile,
            'period' => $this->export->periodLabel(),
        ]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.time-export-delivery', with: [
            'export' => $this->export,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array {
        $bytes = $this->fileBytes;
        $mime = ($this->export->file_format ?? 'csv') === 'csv' ? 'text/csv' : 'application/octet-stream';

        return [
            Attachment::fromData(static fn (): string => $bytes, $this->filename)->withMime($mime),
        ];
    }
}
