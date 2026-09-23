<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubAttendanceSheetStatus, ClubAttendanceStatus, ClubGuardianPermission, ClubParticipationSource, ClubParticipationStatus};
use App\Http\Controllers\Controller;
use App\Models\Calendar\Event;
use App\Models\Club\{ClubAttendanceRecord, ClubExamCandidate, ClubExamOffer, ClubMember, ClubMemberGrade, ClubNotification};
use App\Models\Platform\User;
use App\Services\Club\{ClubAttendanceService, ClubEventService, ClubExamService, ClubGradeCertificatePdfRenderer, ClubGradingService, ClubMatchService, ClubPortalContext, ClubPortalSubject};
use App\Services\UI\DateRangeContext;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * „Mein Verein“ (Feature 159, MVP-845): eigene Termine mit An-/Abmeldung,
 * Änderungen, bestätigte Anwesenheit — für das verknüpfte Mitglied oder als
 * ausdrücklich gewählte Vertretung. Keine fremden Mitglieder, Kontaktdaten
 * oder Fehlzeiten. Fachlogik in ClubEventService/ClubAttendanceService.
 */
class ClubMyController extends Controller {
    public function __construct(
        private readonly ClubPortalContext $context,
        private readonly ClubEventService $clubEvents,
        private readonly ClubAttendanceService $attendance,
    ) {}

    public function index(DateRangeContext $range): View|RedirectResponse {
        Gate::authorize('club-my');
        if ($this->context->current($this->actor()) === null) {
            // Nur zahlungspflichtig, kein Mitglied/Vertretung → direkt zu den Beiträgen (MVP-851).
            return redirect()->route('club.my.fees');
        }
        $subject = $this->subject();
        $member = $subject->member;
        $now = CarbonImmutable::now();
        $current = $range->current();

        $upcoming = $this->clubEvents->visibleEventsFor($member, $now, $now->addDays(90));
        $recent = $this->clubEvents->visibleEventsFor($member, $now->subDays(14), $now)
            ->filter(fn(array $row): bool => $row['participation'] !== null)
            ->reverse()
            ->take(10)
            ->values();
        $changes = ClubNotification::query()
            ->where('club_member_id', $member->id)
            ->whereIn('kind', [ClubNotification::KIND_RESCHEDULED, ClubNotification::KIND_CANCELLED, ClubNotification::KIND_PROMOTED])
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->unique('dedupe_key')
            ->take(10)
            ->values();

        return view('club.my.index', [
            'subject' => $subject,
            'subjects' => $this->context->subjectsFor($this->actor()),
            'upcoming' => $upcoming,
            'recent' => $recent,
            'changes' => $changes,
            'canRegister' => $subject->allows(ClubGuardianPermission::Register),
            'canViewAttendance' => $subject->allows(ClubGuardianPermission::ViewAttendance),
            'creditedMinutes' => $this->attendance->creditableMinutes($member, $current['from'], $current['to']),
            'range' => $current,
            'grading' => $this->gradingOverview($member),
            'hasFeeAccounts' => \App\Models\Club\ClubFeeAccount::query()->where('user_id', $this->actor()->id)->exists(),
            // Spieltage (MVP-852): Verfügbarkeit und Nominierung des Mitglieds.
            'matches' => $this->matchRows($member, $upcoming),
            // Reitbetrieb (MVP-854): eigenes Pferd je Stunde.
            'horseByEvent' => \App\Models\Club\ClubHorseAssignment::query()->where('club_member_id', $member->id)->whereIn('event_id', $upcoming->pluck('event.id')->all())->with('horse:id,name')->get()->keyBy('event_id'),
            // Wettkämpfe (MVP-855): angebotene Disziplinen und eigene Meldungen je Termin.
            'competitionByEvent' => \App\Models\Club\ClubCompetitionDetails::query()->whereIn('event_id', $upcoming->pluck('event.id')->all())->with('profile')->get()->keyBy('event_id'),
            'entriesByEvent' => \App\Models\Club\ClubCompetitionEntry::query()->where('club_member_id', $member->id)->whereIn('event_id', $upcoming->pluck('event.id')->all())->get()->groupBy('event_id'),
            // Prüfungen (MVP-847): eigene Kandidaturen je Termin.
            'candidates' => ClubExamCandidate::query()->where('club_member_id', $member->id)->with('offer:id,event_id')->get()->keyBy(fn(ClubExamCandidate $c): int => (int) ($c->offer->event_id ?? 0)),
        ]);
    }

    /**
     * Prüfung anfragen (MVP-847): erfüllte Voraussetzungen → Zulassung mit Platz,
     * fehlende → Anfrage an die Leitung ohne Platz. Zielgrad ist der nächste
     * angebotene Grad über dem aktuellen.
     */
    public function examRequest(Event $event): RedirectResponse {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::Register), 403);
        $details = $event->clubDetails()->first();
        abort_if($details === null, 404);
        Gate::authorize('registerMember', [$details, $subject->member]);
        $offer = ClubExamOffer::query()->where('event_id', $event->id)->with(['system', 'targetGrades'])->first();
        abort_if($offer === null, 404);

        $grading = app(ClubGradingService::class);
        $current = $grading->currentGrade($subject->member, $offer->system()->firstOrFail(), CarbonImmutable::today());
        $currentRank = $current?->grade->rank ?? -1;
        $target = $offer->targetGrades->first(fn($grade): bool => $grade->rank > $currentRank);
        if ($target === null) {
            return redirect()->route('club.my.index')->withErrors(['target_grade_id' => __('club.exams.error.no_target_grade')]);
        }
        $candidate = app(ClubExamService::class)->addCandidate($offer, $subject->member, $target, $this->actor(), true);

        return redirect()
            ->route('club.my.index')
            ->with('success', __($candidate->status === \App\Enums\Club\ClubExamCandidateStatus::Admitted ? 'club.exams.flash.self_admitted' : 'club.exams.flash.self_requested', ['title' => $event->title]));
    }

    /** Verfügbarkeit für einen Spieltag (MVP-852) — Zusage ist keine Nominierung; nur Kader/Zielgruppe. */
    public function availability(Request $request, Event $event): RedirectResponse {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::Register), 403);
        $data = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\Club\ClubAvailabilityStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $matches = app(ClubMatchService::class);
        abort_unless($event->clubDetails?->kind === \App\Enums\Club\ClubEventKind::Match, 404);
        abort_unless($matches->candidatesFor($event)->contains(fn(array $row): bool => $row['member']->id === $subject->member->id), 403);
        $matches->setAvailability($event, $subject->member, \App\Enums\Club\ClubAvailabilityStatus::from((string) $data['status']), $this->actor(), $data['note'] ?? null);

        return redirect()->route('club.my.index')->with('success', __('club.matches.flash.availability_saved'));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{event: Event, participation: \App\Models\Club\ClubEventParticipation|null, eligible: bool}>  $upcoming
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function matchRows(ClubMember $member, \Illuminate\Support\Collection $upcoming): \Illuminate\Support\Collection {
        $events = $upcoming->pluck('event')->filter(fn(Event $event): bool => $event->clubDetails?->kind === \App\Enums\Club\ClubEventKind::Match)->values();
        if ($events->isEmpty()) {
            return collect();
        }
        $ids = $events->pluck('id')->all();
        $details = \App\Models\Club\ClubMatchDetails::query()->whereIn('event_id', $ids)->with('team:id,name')->get()->keyBy('event_id');
        $availabilities = \App\Models\Club\ClubMatchAvailability::query()->whereIn('event_id', $ids)->where('club_member_id', $member->id)->get()->keyBy('event_id');
        $entries = \App\Models\Club\ClubLineupEntry::query()->whereIn('event_id', $ids)->where('club_member_id', $member->id)->get()->groupBy('event_id');

        $rows = collect();
        foreach ($events as $event) {
            $match = $details->get($event->id);
            if (! $match instanceof \App\Models\Club\ClubMatchDetails) {
                continue;
            }
            $rows->push([
                'event' => $event,
                'match' => $match,
                'availability' => $availabilities->get($event->id),
                'nominated' => $match->isReleased() ? collect($entries->get($event->id, [])) : collect(),
            ]);
        }

        return $rows;
    }
    /** Wettkampfmeldung durch das Mitglied (MVP-855): ohne Startrecht „zur Klärung“, nie still abgelehnt. */
    public function compete(Request $request, Event $event): RedirectResponse {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::Register), 403);
        abort_unless($event->clubDetails?->kind === \App\Enums\Club\ClubEventKind::Competition, 404);
        $data = $request->validate(['disciplines' => ['required', 'array', 'min:1', 'max:50'], 'disciplines.*' => ['string', 'max:30']]);
        $competitions = app(\App\Services\Club\ClubCompetitionService::class);
        abort_unless($this->clubEvents->isEligible($event, $subject->member), 403);
        $entries = $competitions->enter($event, $subject->member, array_values((array) $data['disciplines']), $this->actor(), $subject->isSelf() ? \App\Enums\Club\ClubParticipationSource::Self : \App\Enums\Club\ClubParticipationSource::Guardian);
        $review = $entries->contains(fn(\App\Models\Club\ClubCompetitionEntry $e): bool => $e->status === \App\Enums\Club\ClubEntryStatus::NeedsReview);

        return redirect()->route('club.my.index')->with($review ? 'warning' : 'success', __($review ? 'club.competitions.flash.entered_review' : 'club.competitions.flash.entered', ['name' => $subject->member->fullName(), 'count' => $entries->count()]));
    }
    /** Meine Beiträge (MVP-851): nur Konten, deren zahlungspflichtige Person ausdrücklich dieser Nutzer ist. */
    public function fees(): View {
        Gate::authorize('club-my');
        $payments = app(\App\Services\Club\ClubFeePaymentService::class);
        $runs = app(\App\Services\Club\ClubFeeRunService::class);
        $accounts = \App\Models\Club\ClubFeeAccount::query()->where('user_id', $this->actor()->id)->orderBy('name')->get()
            ->map(fn(\App\Models\Club\ClubFeeAccount $account): array => [
                'account' => $account,
                'open' => $runs->openAmountFor($account),
                'credit' => $payments->creditBalance($account),
                'claims' => \App\Models\Club\ClubFeeClaim::query()->where('club_fee_account_id', $account->id)->orderByDesc('sequence')->limit(24)->get(),
                'payments' => \App\Models\Club\ClubFeePayment::query()->where('club_fee_account_id', $account->id)->with('claim:id,number')->orderByDesc('paid_on')->limit(24)->get(),
            ]);

        return view('club.my.fees', ['accounts' => $accounts, 'hasSubjects' => $this->context->hasSubjects($this->actor()), 'today' => CarbonImmutable::today()]);
    }

    public function feePdf(\App\Models\Club\ClubFeeClaim $claim): Response {
        Gate::authorize('club-my');
        $account = $claim->account()->first();
        abort_unless($account !== null && $account->user_id === $this->actor()->id, 403);
        $renderer = app(\App\Services\Club\ClubFeeNoticePdfRenderer::class);

        return response($renderer->output($claim), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($claim) . '"',
        ]);
    }

    /** Eigene Bescheinigung (MVP-847). */
    public function certificate(ClubMemberGrade $memberGrade): Response {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($memberGrade->club_member_id === $subject->member->id, 404);
        $renderer = app(ClubGradeCertificatePdfRenderer::class);

        return response($renderer->output($memberGrade), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($memberGrade) . '"',
        ]);
    }

    /**
     * Graduierung (MVP-846): aktueller Grad und Fortschritt zum nächsten je Ordnung — nur bei aktivem Modul.
     *
     * @return \Illuminate\Support\Collection<int, array{system: \App\Models\Club\ClubGradingSystem, current: \App\Models\Club\ClubMemberGrade|null, next: \App\Models\Club\ClubGrade|null, report: \App\Services\Club\ClubEligibilityReport|null}>
     */
    private function gradingOverview(ClubMember $member) {
        $grading = app(\App\Services\Club\ClubGradingService::class);
        if (! $grading->isEnabled($member->organization)) {
            return collect();
        }
        $today = CarbonImmutable::today();

        return \App\Models\Club\ClubGradingSystem::query()->where('is_active', true)->orderBy('name')->get()
            ->map(function (\App\Models\Club\ClubGradingSystem $system) use ($grading, $member, $today): array {
                $requirement = $grading->nextRequirement($member, $system, $today);

                return [
                    'system' => $system,
                    'current' => $grading->currentGrade($member, $system, $today),
                    'next' => $requirement?->grade,
                    'report' => $requirement !== null ? app(\App\Services\Club\ClubEligibilityService::class)->evaluate($member, $requirement, $today) : null,
                ];
            })
            ->filter(fn(array $row): bool => $row['current'] !== null || $row['report'] !== null)
            ->values();
    }

    /** Vertretungen wählen das betreute Mitglied ausdrücklich. */
    public function select(Request $request): RedirectResponse {
        Gate::authorize('club-my');
        $data = $request->validate(['club_member_id' => ['required', 'string', 'max:64']]);
        $memberId = Sqid::decodeOrNumeric(ClubMember::class, (string) $data['club_member_id']);
        abort_if($memberId === null || $this->context->select($this->actor(), $memberId) === null, 404);

        return redirect()->route('club.my.index');
    }

    public function register(Event $event): RedirectResponse {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::Register), 403);
        $details = $event->clubDetails()->first();
        abort_if($details === null, 404);
        Gate::authorize('registerMember', [$details, $subject->member]);

        $source = $subject->isSelf() ? ClubParticipationSource::Self : ClubParticipationSource::Guardian;
        $participation = $this->clubEvents->register($event, $subject->member, $this->actor(), $source, $subject->guardian);

        return redirect()
            ->route('club.my.index')
            ->with('success', __($participation->status === ClubParticipationStatus::Waitlisted ? 'club.my.flash.waitlisted' : 'club.my.flash.registered', ['title' => $event->title]));
    }

    public function cancel(Event $event): RedirectResponse {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::Register), 403);
        $details = $event->clubDetails()->first();
        abort_if($details === null, 404);
        Gate::authorize('registerMember', [$details, $subject->member]);

        $this->clubEvents->cancelRegistration($event, $subject->member, $this->actor());

        return redirect()
            ->route('club.my.index')
            ->with('success', __('club.my.flash.cancelled', ['title' => $event->title]));
    }

    /** Eigene bestätigte Nachweise im Kopfzeilen-Zeitraum — nur Anwesenheit, keine Fehlzeiten anderer. */
    public function attendance(DateRangeContext $range): View {
        Gate::authorize('club-my');
        $subject = $this->subject();
        abort_unless($subject->allows(ClubGuardianPermission::ViewAttendance), 403);
        $member = $subject->member;
        $current = $range->current();

        $records = ClubAttendanceRecord::query()
            ->where('club_member_id', $member->id)
            ->with(['event:id,title,started_at,ended_at', 'sheet:id,status'])
            ->whereHas('sheet', fn(Builder $sheet) => $sheet->where('status', ClubAttendanceSheetStatus::Confirmed->value))
            ->whereHas('event', fn(Builder $event) => $event
                ->where('started_at', '>=', $current['from']->startOfDay()->utc())
                ->where('started_at', '<', $current['to']->addDay()->startOfDay()->utc()))
            ->orderByDesc('event_id')
            ->paginate(50)
            ->withQueryString();

        return view('club.my.attendance', [
            'subject' => $subject,
            'records' => $records,
            'creditedMinutes' => $this->attendance->creditableMinutes($member, $current['from'], $current['to']),
            'creditableStatuses' => [ClubAttendanceStatus::Present, ClubAttendanceStatus::Partial],
            'range' => $current,
        ]);
    }

    private function subject(): ClubPortalSubject {
        $subject = $this->context->current($this->actor());
        abort_if($subject === null, 403);

        return $subject;
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
