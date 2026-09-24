<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubExamController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubEventVisibility, ClubExamCandidateStatus, ClubParticipationSource};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{AddExamCandidateRequest, SaveExamOfferRequest};
use App\Models\Club\{ClubDepartment, ClubExamCandidate, ClubExamOffer, ClubGrade, ClubGradingSystem, ClubGroup, ClubMember};
use App\Models\Facility\Room;
use App\Models\Platform\User;
use App\Services\Club\{ClubEventService, ClubExamService};
use App\Services\UI\DateRangeContext;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Prüfungsangebote und Kandidaten (Feature 159, MVP-847): Angebot als Termin
 * mit eingefrorener Regelversion, Kandidaten mit Zulassungsbericht,
 * Freigabe, Zulassung (auch als Ausnahme), Ablehnung, Ergebnis. Fachlogik im
 * ClubExamService.
 */
class ClubExamController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubExamService $exams,
        private readonly ClubEventService $clubEvents,
    ) {}

    public function index(Request $request, DateRangeContext $range): View {
        Gate::authorize('viewAny', ClubExamOffer::class);
        $period = in_array($request->query('period'), ['upcoming', 'range', 'past'], true) ? (string) $request->query('period') : 'upcoming';
        $current = $range->current();
        $now = now();

        $offers = ClubExamOffer::query()
            ->with(['event', 'system:id,name,discipline', 'version:id,version_no', 'targetGrades:id,name,rank'])
            ->withCount(['candidates', 'candidates as admitted_count' => fn($q) => $q->where('status', ClubExamCandidateStatus::Admitted->value)])
            ->whereHas('event', function ($event) use ($period, $current, $now): void {
                match ($period) {
                    'past' => $event->where('ended_at', '<', $now),
                    'range' => $event->where('started_at', '>=', $current['from']->startOfDay()->utc())->where('started_at', '<', $current['to']->addDay()->startOfDay()->utc()),
                    default => $event->where('ended_at', '>=', $now),
                };
            })
            ->get()
            ->sortBy(fn(ClubExamOffer $offer) => $offer->event?->started_at)
            ->values();

        return view('club.exams.index', [
            'offers' => $offers,
            'period' => $period,
            'canCreate' => Gate::allows('create', ClubExamOffer::class),
        ]);
    }

    public function show(ClubExamOffer $offer): View {
        Gate::authorize('view', $offer);
        $offer->load(['event.clubGroups:id,name', 'system', 'version', 'targetGrades']);
        $candidates = $offer->candidates()->with(['member', 'targetGrade', 'resultBy:id,name', 'awardedGrade'])->get()
            ->sortBy(fn(ClubExamCandidate $c) => $c->member?->last_name . ' ' . $c->member?->first_name)->values();

        return view('club.exams.show', [
            'offer' => $offer,
            'event' => $offer->event,
            'candidates' => $candidates,
            'examiners' => User::query()->whereIn('id', $offer->examinerIds())->get(['id', 'name']),
            'canManage' => Gate::allows('update', $offer),
            'canCandidates' => Gate::allows('manageCandidates', $offer),
            'canException' => Gate::allows('admitByException', $offer),
            'canResult' => Gate::allows('recordResult', $offer) && ! $offer->event?->isCancelled(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubExamOffer::class);

        return view('club.exams._offer_dialog', ['offer' => null, 'event' => null] + $this->formOptions());
    }

    public function store(SaveExamOfferRequest $request): RedirectResponse {
        Gate::authorize('create', ClubExamOffer::class);
        /** @var User $actor */
        $actor = Auth::user();
        $offer = $this->exams->createOffer($this->currentOrganization(), $actor, $this->offerData($request->validated()));

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.offer_saved'));
    }

    public function edit(ClubExamOffer $offer): View {
        Gate::authorize('update', $offer);

        return view('club.exams._offer_dialog', ['offer' => $offer->load(['targetGrades', 'event.clubGroups', 'event.clubDetails']), 'event' => $offer->event] + $this->formOptions());
    }

    public function update(SaveExamOfferRequest $request, ClubExamOffer $offer): RedirectResponse {
        Gate::authorize('update', $offer);
        /** @var User $actor */
        $actor = Auth::user();
        $this->exams->updateOffer($offer, $actor, $this->offerData($request->validated()));

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.offer_saved'));
    }

    // ── Kandidaten ───────────────────────────────────────────────────────

    public function candidateDialog(ClubExamOffer $offer): View {
        Gate::authorize('manageCandidates', $offer);
        $offer->load(['event', 'targetGrades']);
        $known = $offer->candidates()->pluck('club_member_id');
        $targets = $offer->event !== null ? $this->clubEvents->targetMembers($offer->event)->whereNotIn('id', $known) : collect();
        $others = ClubMember::query()->current()->whereNotIn('id', $known->merge($targets->pluck('id')))->orderBy('last_name')->orderBy('first_name')->get();

        return view('club.exams._candidate_dialog', ['offer' => $offer, 'targets' => $targets, 'others' => $others]);
    }

    public function storeCandidate(AddExamCandidateRequest $request, ClubExamOffer $offer): RedirectResponse {
        Gate::authorize('manageCandidates', $offer);
        $data = $request->validated();
        /** @var ClubMember $member */
        $member = ClubMember::query()->findOrFail((int) $data['club_member_id']);
        /** @var ClubGrade $grade */
        $grade = ClubGrade::query()->findOrFail((int) $data['target_grade_id']);
        /** @var User $actor */
        $actor = Auth::user();
        $this->exams->addCandidate($offer, $member, $grade, $actor);

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.candidate_added', ['name' => $member->fullName()]));
    }

    public function approve(ClubExamOffer $offer, ClubExamCandidate $candidate): RedirectResponse {
        Gate::authorize('manageCandidates', $offer);
        abort_unless($candidate->club_exam_offer_id === $offer->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->exams->approve($candidate, $actor);

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.approved'));
    }

    public function admit(Request $request, ClubExamOffer $offer, ClubExamCandidate $candidate): RedirectResponse {
        Gate::authorize('manageCandidates', $offer);
        abort_unless($candidate->club_exam_offer_id === $offer->id, 404);
        $data = $request->validate(['exception_reason' => ['nullable', 'string', 'max:255']]);
        $reason = isset($data['exception_reason']) && trim((string) $data['exception_reason']) !== '' ? trim((string) $data['exception_reason']) : null;
        if ($reason !== null) {
            Gate::authorize('admitByException', $offer);
        }
        /** @var User $actor */
        $actor = Auth::user();
        $this->exams->admit($candidate, $actor, ClubParticipationSource::Leader, $reason);

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.admitted'));
    }

    public function exceptionDialog(ClubExamOffer $offer, ClubExamCandidate $candidate): View {
        Gate::authorize('admitByException', $offer);
        abort_unless($candidate->club_exam_offer_id === $offer->id, 404);

        return view('club.exams._exception_dialog', ['offer' => $offer, 'candidate' => $candidate->load('member')]);
    }

    public function reject(Request $request, ClubExamOffer $offer, ClubExamCandidate $candidate): RedirectResponse {
        Gate::authorize('manageCandidates', $offer);
        abort_unless($candidate->club_exam_offer_id === $offer->id, 404);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->exams->reject($candidate, $actor, $data['note'] ?? null);

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.rejected'));
    }

    public function recheck(ClubExamOffer $offer): RedirectResponse {
        Gate::authorize('manageCandidates', $offer);
        $flagged = $this->exams->recheckOffer($offer, (string) __('club.exams.label.review_manual'));

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.rechecked', ['count' => $flagged]));
    }

    public function clearReview(Request $request, ClubExamOffer $offer, ClubExamCandidate $candidate): RedirectResponse {
        Gate::authorize('manageCandidates', $offer);
        abort_unless($candidate->club_exam_offer_id === $offer->id, 404);
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->exams->clearReview($candidate, $actor, (string) $data['note']);

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.review_cleared'));
    }

    public function resultDialog(ClubExamOffer $offer, ClubExamCandidate $candidate): View {
        Gate::authorize('recordResult', $offer);
        abort_unless($candidate->club_exam_offer_id === $offer->id, 404);

        return view('club.exams._result_dialog', ['offer' => $offer, 'candidate' => $candidate->load(['member', 'targetGrade']), 'results' => [ClubExamCandidateStatus::Passed, ClubExamCandidateStatus::Failed, ClubExamCandidateStatus::NoShow]]);
    }

    public function storeResult(Request $request, ClubExamOffer $offer, ClubExamCandidate $candidate): RedirectResponse {
        Gate::authorize('recordResult', $offer);
        abort_unless($candidate->club_exam_offer_id === $offer->id, 404);
        $data = $request->validate([
            'result' => ['required', 'string', \Illuminate\Validation\Rule::in([ClubExamCandidateStatus::Passed->value, ClubExamCandidateStatus::Failed->value, ClubExamCandidateStatus::NoShow->value])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->exams->recordResult($candidate, ClubExamCandidateStatus::from((string) $data['result']), $actor, $data['note'] ?? null);

        return redirect()->route('club.exams.show', $offer)->with('success', __('club.exams.flash.result_saved'));
    }

    /** @return array<string, mixed> */
    private function formOptions(): array {
        return [
            'systems' => ClubGradingSystem::query()->where('is_active', true)->with('grades')->orderBy('name')->get(),
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => ClubDepartment::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name']),
            'visibilities' => ClubEventVisibility::cases(),
        ];
    }

    /**
     * Listen-Sqids (Zielgrade, Prüfer, Gruppen) dekodieren; Zeiten kommen als lokale Werte und werden wie beim Vereinstermin in UTC übergeben.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function offerData(array $data): array {
        $data['target_grade_ids'] = $this->decodeList(ClubGrade::class, (array) ($data['target_grade_ids'] ?? []));
        $data['examiner_user_ids'] = $this->decodeList(User::class, (array) ($data['examiner_user_ids'] ?? []));
        $data['club_group_ids'] = $this->decodeList(ClubGroup::class, (array) ($data['club_group_ids'] ?? []));
        $tz = trim((string) ($data['timezone'] ?? ''));
        $tz = \App\Support\Tz::isValid($tz) && $tz !== 'UTC' ? $tz : \App\Support\Tz::current();
        foreach (['started_at', 'ended_at'] as $key) {
            $data[$key] = \Carbon\CarbonImmutable::parse((string) $data[$key], $tz)->utc()->format('Y-m-d H:i:s');
        }
        $data['timezone'] = $tz;

        return $data;
    }

    /**
     * @param  class-string  $class
     * @param  array<int|string, mixed>  $values
     * @return list<int>
     */
    private function decodeList(string $class, array $values): array {
        return array_values(array_filter(array_map(
            static fn(string $sqid): ?int => Sqid::decodeOrNumeric($class, $sqid),
            array_map('strval', $values),
        )));
    }
}
