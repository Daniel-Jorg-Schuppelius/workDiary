<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetSignatureRequestedMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Mail;

use App\Models\Timesheet;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class TimesheetSignatureRequestedMail extends Mailable {
    use Queueable, SerializesModels;

    public function __construct(public Timesheet $timesheet, public string $signUrl) {}

    public function envelope(): Envelope {
        return new Envelope(subject: __('Stundenzettel zur Gegenzeichnung'));
    }

    public function content(): Content {
        return new Content(view: 'mail.timesheet-signature-requested', with: [
            'timesheet' => $this->timesheet,
            'signUrl' => $this->signUrl,
        ]);
    }
}
