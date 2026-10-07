<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeNoticeMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Customer\CustomerIntake;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Nachricht an den Portalzugang zu einem Kundeneingang (MVP-1074/1075):
 * Eingangsbestätigung, Rückfrage, Angebot, Ablehnung, Übernahme oder
 * Druckfreigabe. Die Eingangsbestätigung bestätigt nur den Eingang — keine
 * Auftragsannahme und keinen Wunschtermin. Nie interne Notizen oder Kalkulation.
 */
class CustomerIntakeNoticeMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly CustomerIntake $intake, public readonly string $notice) {}

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('customer_intake.mail.' . $this->notice . '.subject', ['number' => $this->intake->number]));
    }

    public function content(): Content {
        return new Content(markdown: 'mail.customer-intake-notice', with: [
            'intake' => $this->intake,
            'notice' => $this->notice,
        ]);
    }
}
