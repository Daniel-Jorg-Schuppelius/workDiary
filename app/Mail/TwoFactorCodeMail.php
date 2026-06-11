<?php
/*
 * Created on   : Tue Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorCodeMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/** Einmalcode für die Zwei-Faktor-Bestätigung per E-Mail. */
class TwoFactorCodeMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $code, public readonly int $validMinutes = 10) {}

    public function envelope(): Envelope {
        // Der Code steht bewusst NICHT im Betreff: Betreffzeilen erscheinen in
        // Push-/Sperrbildschirm-Vorschauen, Mail-Logs und Gateways und würden
        // den zweiten Faktor exponieren. Der Code wird nur im Body übermittelt.
        return new Envelope(subject: __('Ihr Bestätigungscode'));
    }

    public function content(): Content {
        return new Content(view: 'emails.two-factor-code');
    }
}
