<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DsarDeadlineExtensionMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail\Privacy;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};

/**
 * Mitteilung der Fristverlängerung nach Art. 12 Abs. 3 DSGVO (MVP-1602): neue
 * Frist und Grund, nichts aus der Anfrage selbst. Bewusst nicht über die Queue:
 * Der Grund liegt am Fall verschlüsselt und soll nicht im Klartext in der
 * Job-Tabelle stehen.
 */
class DsarDeadlineExtensionMail extends Mailable {
    public function __construct(
        public string $requestNumber,
        public string $organizationName,
        public string $deadlineDate,
        public string $reason,
    ) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('dsar.extension_mail.subject', ['nr' => $this->requestNumber]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.privacy.dsar-deadline-extension', with: [
            'requestNumber' => $this->requestNumber,
            'organizationName' => $this->organizationName,
            'deadlineDate' => $this->deadlineDate,
            'reason' => $this->reason,
        ]);
    }
}
