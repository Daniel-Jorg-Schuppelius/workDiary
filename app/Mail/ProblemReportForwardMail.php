<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReportForwardMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\ProblemReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Weiterleitung einer Fehlermeldung an die konfigurierte Support-Adresse
 * (Feature 041, MVP-053). Der redaktierte Datensatz hängt als JSON an —
 * der Mailtext bleibt bewusst kurz (Referenz + Zusammenfassung).
 */
class ProblemReportForwardMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly ProblemReport $report) {}

    public function envelope(): Envelope {
        return new Envelope(
            subject: '[' . $this->report->reference_no . '] ' . $this->report->summary,
        );
    }

    public function content(): Content {
        return new Content(markdown: 'emails.problem-report-forward', with: [
            'report' => $this->report,
        ]);
    }

    /** @return list<Attachment> */
    public function attachments(): array {
        return [
            Attachment::fromData(
                fn(): string => (string) json_encode($this->report->exportPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                $this->report->reference_no . '.json',
            )->withMime('application/json'),
        ];
    }
}
