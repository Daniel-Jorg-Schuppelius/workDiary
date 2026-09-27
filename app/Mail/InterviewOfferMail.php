<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InterviewOfferMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Applications\{JobApplication, JobInterviewOffer};
use App\Models\Platform\Organization;
use App\Support\{OrganizationContext, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/** Einladung zur Terminwahl (MVP-925) mit Link und den angebotenen Terminen. */
class InterviewOfferMail extends Mailable implements ShouldQueue {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $offerId, public readonly string $token) {
        $this->afterCommit();
    }

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('recruiting.offer.mail.subject', ['title' => (string) $this->application()->requisition?->title]));
    }

    public function content(): Content {
        $offer = JobInterviewOffer::query()->withoutGlobalScopes()->findOrFail($this->offerId);
        $application = $this->application();
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($offer->organization_id);
        $slots = OrganizationContext::run($organization, static fn (): string => implode("\n", array_map(
            static fn (string $s): string => '– ' . CarbonImmutable::parse($s)->setTimezone(Tz::current())->format('d.m.Y H:i'),
            $offer->slots,
        )));
        $text = (string) __('recruiting.offer.mail.body', [
            'name' => (string) $application->candidate_name,
            'slots' => $slots,
            'url' => route('interview-offers.show', $this->token),
            'until' => $offer->expires_at->format('d.m.Y'),
        ]);

        return new Content(view: 'mail.document', text: 'mail.document-text', with: ['html' => nl2br(e($text)), 'text' => $text]);
    }

    private function application(): JobApplication {
        $offer = JobInterviewOffer::query()->withoutGlobalScopes()->findOrFail($this->offerId);

        return JobApplication::query()->withoutGlobalScopes()->with('requisition')->findOrFail($offer->job_application_id);
    }
}
