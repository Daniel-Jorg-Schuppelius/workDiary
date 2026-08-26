<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRequestDecisionMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Enums\Rental\RentalRequestStatus;
use App\Models\Rental\RentalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Entscheidung zu einer Portal-Verleihanfrage (Feature 073, MVP-714):
 * Annahme nennt Gerät und Zeitraum, Ablehnung den Grund — nie interne
 * Akten-, Kosten- oder Fremdbelegungsdetails.
 */
class RentalRequestDecisionMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly RentalRequest $request) {}

    public function envelope(): Envelope {
        $accepted = $this->request->status === RentalRequestStatus::Accepted;

        return new Envelope(subject: $accepted
            ? (string) __('Verleih-Anfrage angenommen: :subject', ['subject' => $this->request->subjectLabel()])
            : (string) __('Ihre Verleih-Anfrage: :subject', ['subject' => $this->request->subjectLabel()]));
    }

    public function content(): Content {
        return new Content(view: 'mail.rental-request-decision', with: [
            'request' => $this->request,
            'accepted' => $this->request->status === RentalRequestStatus::Accepted,
        ]);
    }
}
