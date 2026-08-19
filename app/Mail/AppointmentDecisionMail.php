<?php
/*
 * Created on   : Tue Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppointmentDecisionMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\AppointmentRequest;
use App\Services\Event\IcsFeedService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

/**
 * Entscheidung zu einer Terminanfrage (Feature 087, Folgepunkt aus MVP-667):
 * Die Bestätigung trägt den Termin als **ICS-Anhang** — der Kunde legt ihn
 * mit einem Klick in seinen Kalender; die Ablehnung nennt den Grund.
 */
class AppointmentDecisionMail extends Mailable {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly AppointmentRequest $request) {}

    public function envelope(): Envelope {
        $confirmed = $this->request->status === AppointmentRequest::STATUS_CONFIRMED;

        return new Envelope(subject: $confirmed
            ? (string) __('Terminbestätigung: :service am :date', [
                'service' => $this->request->service_label,
                'date' => $this->request->start_at?->format('d.m.Y H:i'),
            ])
            : (string) __('Ihre Terminanfrage: :service', ['service' => $this->request->service_label]));
    }

    public function content(): Content {
        return new Content(markdown: 'emails.appointment-decision');
    }

    /** @return list<Attachment> */
    public function attachments(): array {
        if ($this->request->status !== AppointmentRequest::STATUS_CONFIRMED) {
            return [];
        }

        return [
            Attachment::fromData(
                fn (): string => app(IcsFeedService::class)->documentForAppointment($this->request),
                'termin.ics',
            )->withMime('text/calendar'),
        ];
    }
}
