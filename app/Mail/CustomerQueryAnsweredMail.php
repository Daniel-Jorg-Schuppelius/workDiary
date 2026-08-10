<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerQueryAnsweredMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\CustomerQuery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Benachrichtigt den Fragesteller über die Antwort auf seine Portal-Rückfrage
 * (MVP-512). Enthält bewusst nur die eigene Frage/Antwort — keine internen
 * Notizen, keine Daten Dritter.
 */
class CustomerQueryAnsweredMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public CustomerQuery $query) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('Antwort auf Ihre Rückfrage'));
    }

    public function content(): Content {
        return new Content(view: 'mail.customer-query-answered', with: [
            'query' => $this->query,
        ]);
    }
}
