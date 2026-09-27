<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InterviewConfirmedMail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Applications\{JobApplication, JobApplicationInterview};
use App\Models\Platform\Organization;
use App\Services\Event\IcsFeedService;
use App\Support\{OrganizationContext, Tz};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\{Attachment, Mailable};
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

/** Bestätigung des gewählten Gesprächstermins (MVP-925) mit ICS-Anhang. */
class InterviewConfirmedMail extends Mailable implements ShouldQueue {
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $interviewId, public readonly int $durationMinutes) {
        $this->afterCommit();
    }

    public function envelope(): Envelope {
        return new Envelope(subject: (string) __('recruiting.offer.mail.confirmed_subject'));
    }

    public function content(): Content {
        $interview = $this->interview();
        $application = JobApplication::query()->withoutGlobalScopes()->findOrFail($interview->job_application_id);
        $when = OrganizationContext::run(Organization::query()->withoutGlobalScopes()->findOrFail($interview->organization_id), static fn (): string => $interview->scheduled_at->copy()->setTimezone(Tz::current())->format('d.m.Y H:i'));
        $text = (string) __('recruiting.offer.mail.confirmed_body', [
            'name' => (string) $application->candidate_name,
            'when' => $when,
            'mode' => __('values.' . $interview->mode),
        ]);

        return new Content(view: 'mail.document', text: 'mail.document-text', with: ['html' => nl2br(e($text)), 'text' => $text]);
    }

    /** @return list<Attachment> */
    public function attachments(): array {
        $interview = $this->interview();

        return [Attachment::fromData(fn (): string => app(IcsFeedService::class)->documentForInterview($interview, $this->durationMinutes), 'gespraech.ics')->withMime('text/calendar')];
    }

    private function interview(): JobApplicationInterview {
        return JobApplicationInterview::query()->withoutGlobalScopes()->with('organization')->findOrFail($this->interviewId);
    }
}
