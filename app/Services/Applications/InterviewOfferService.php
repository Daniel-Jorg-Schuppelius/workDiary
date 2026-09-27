<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InterviewOfferService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications;

use App\Mail\{InterviewConfirmedMail, InterviewOfferMail};
use App\Models\Applications\{JobApplication, JobApplicationInterview, JobInterviewOffer};
use App\Models\Platform\User;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\{CryptoHelper, EmailHelper};
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Terminwahl durch Bewerber (MVP-925): angebotene Termine per Link, der
 * Bewerber wählt einen, daraus wird ein Gespräch mit Bestätigung und ICS.
 * Der Link gilt bis zum Ablauf oder zur ersten Wahl.
 */
final class InterviewOfferService {
    /** @param list<CarbonImmutable> $slots UTC */
    public function offer(JobApplication $application, array $slots, string $mode, int $durationMinutes, ?int $interviewerUserId, CarbonImmutable $expiresAt, User $actor): JobInterviewOffer {
        $email = trim((string) $application->email);
        if (! EmailHelper::isEmail($email)) {
            throw new RuntimeException((string) __('recruiting.offer.error.no_email'));
        }
        if ($slots === []) {
            throw new RuntimeException((string) __('recruiting.offer.error.no_slots'));
        }
        usort($slots, static fn (CarbonImmutable $a, CarbonImmutable $b): int => $a <=> $b);
        $token = Str::lower(Str::random(40));

        return DB::transaction(function () use ($application, $slots, $mode, $durationMinutes, $interviewerUserId, $expiresAt, $actor, $email, $token): JobInterviewOffer {
            $offer = JobInterviewOffer::query()->create([
                'organization_id' => $application->organization_id,
                'job_application_id' => $application->id,
                'token_hash' => CryptoHelper::hash($token),
                'slots' => array_map(static fn (CarbonImmutable $s): string => $s->utc()->toIso8601String(), $slots),
                'mode' => $mode,
                'duration_minutes' => $durationMinutes,
                'interviewer_user_id' => $interviewerUserId,
                'expires_at' => $expiresAt,
                'created_by' => $actor->id,
            ]);
            $application->audit('recruiting.interview_offered', ['slots' => count($slots), 'by' => $actor->id]);
            Mail::to($email)->queue(new InterviewOfferMail((int) $offer->id, $token));

            return $offer;
        });
    }

    /** Offenes Angebot zum Klartext-Token, sonst null (unbekannt, abgelaufen, gewählt). */
    public function resolve(string $token): ?JobInterviewOffer {
        if ($token === '') {
            return null;
        }
        // TENANT-BYPASS: öffentlicher Link, Auflösung ausschließlich über den Abdruck.
        $offer = JobInterviewOffer::query()->withoutGlobalScopes()->where('token_hash', CryptoHelper::hash($token))->first();

        return $offer instanceof JobInterviewOffer && $offer->isOpen() ? $offer : null;
    }

    public function choose(JobInterviewOffer $offer, int $slotIndex): JobApplicationInterview {
        $slot = $offer->slots[$slotIndex] ?? null;
        if ($slot === null || ! $offer->isOpen()) {
            throw new RuntimeException((string) __('recruiting.offer.error.unavailable'));
        }

        return DB::transaction(function () use ($offer, $slot): JobApplicationInterview {
            // Sperre gegen doppelte Wahl über zwei Tabs.
            $locked = JobInterviewOffer::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($offer->id);
            if ($locked->chosen_at !== null) {
                throw new RuntimeException((string) __('recruiting.offer.error.unavailable'));
            }
            $application = JobApplication::query()->withoutGlobalScopes()->findOrFail($offer->job_application_id);
            $interview = JobApplicationInterview::query()->create([
                'organization_id' => $offer->organization_id,
                'job_application_id' => $application->id,
                'scheduled_at' => CarbonImmutable::parse($slot),
                'mode' => $offer->mode,
                'interviewer_id' => $offer->interviewer_user_id,
                'status' => 'planned',
            ]);
            $locked->forceFill(['chosen_at' => now(), 'job_application_interview_id' => $interview->id])->save();
            if (in_array($application->status, JobApplication::PIPELINE_STATUSES, true)) {
                $application->forceFill(['status' => 'interview_planned'])->save();
            }
            $application->audit('recruiting.interview_chosen', ['interview_id' => $interview->id]);
            $email = trim((string) $application->email);
            if (EmailHelper::isEmail($email)) {
                Mail::to($email)->queue(new InterviewConfirmedMail((int) $interview->id, (int) $offer->duration_minutes));
            }

            return $interview;
        });
    }
}
