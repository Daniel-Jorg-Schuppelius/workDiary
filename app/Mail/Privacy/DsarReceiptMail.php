<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DsarReceiptMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail\Privacy;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Eingangsbestaetigung einer ueber das Portal gestellten Betroffenenanfrage
 * (G11, MVP-728). Enthaelt bewusst KEINE Inhalte der Anfrage — die Adresse ist
 * zu diesem Zeitpunkt unbestaetigt und koennte einer dritten Person gehoeren.
 * Uebergeben werden nur Skalare, damit die Queue-Payload keinen Fall traegt.
 */
class DsarReceiptMail extends Mailable implements ShouldQueue {
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $requestNumber,
        public string $organizationName,
        public string $deadlineDate,
        public string $confirmUrl,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('dsar.mail.subject', ['nr' => $this->requestNumber]));
    }

    public function content(): Content {
        return new Content(view: 'mail.privacy.dsar-receipt', with: [
            'requestNumber' => $this->requestNumber,
            'organizationName' => $this->organizationName,
            'deadlineDate' => $this->deadlineDate,
            'confirmUrl' => $this->confirmUrl,
        ]);
    }
}
