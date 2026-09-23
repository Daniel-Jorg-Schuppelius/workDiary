<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubCompetitionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubEntryStatus, ClubEventKind, ClubParticipationSource};
use App\Enums\Event\EventStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{SaveCompetitionEntryRequest, SaveCompetitionRequest, SavePerformanceRequest};
use App\Models\Club\{ClubCompetitionEntry, ClubEventDetails, ClubGroup, ClubMember, ClubPerformance, ClubSportProfile};
use App\Models\{Event, Room, User};
use App\Services\Club\ClubCompetitionService;
use App\Services\UI\DateRangeContext;
use App\Support\{ErrorText, Sqid, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/** Wettkämpfe (Feature 159, MVP-855): Liste, Anlage, Meldungen je Disziplin, Klärung, Ergebnisse. */
class ClubCompetitionController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubCompetitionService $competitions,
    ) {}

    public function index(Request $request, DateRangeContext $range): View {
        Gate::authorize('viewAny', ClubEventDetails::class);
        /** @var User $user */
        $user = Auth::user();
        $period = (string) $request->query('period', 'upcoming');
        $profileSqid = trim((string) $request->query('profile', ''));
        $now = CarbonImmutable::now();
        $query = Event::query()->whereHas('clubCompetition')->with(['clubCompetition.profile:id,name', 'clubGroups:id,name'])->withCount('clubCompetitionEntries as entries_count');
        $profileId = $profileSqid !== '' ? Sqid::decodeOrNumeric(ClubSportProfile::class, $profileSqid) : null;
        if ($profileId !== null) {
            $query->whereHas('clubCompetition', fn(Builder $q) => $q->where('club_sport_profile_id', $profileId));
        }
        if (! Gate::allows('viewRegister', ClubMember::class)) {
            $query->whereHas('clubGroups', fn(Builder $groups) => $groups->where('leader_user_id', $user->id));
        }
        if ($period === 'past') {
            $query->where('ended_at', '<', $now)->orderByDesc('started_at');
        } elseif ($period === 'range') {
            $current = $range->current();
            $query->where('started_at', '>=', CarbonImmutable::instance($current['from'])->startOfDay()->utc())->where('started_at', '<', CarbonImmutable::instance($current['to'])->addDay()->startOfDay()->utc())->orderBy('started_at');
        } else {
            $period = 'upcoming';
            $query->where('ended_at', '>=', $now)->orderBy('started_at');
        }

        return view('club.competitions.index', [
            'events' => $query->paginate(30)->withQueryString(),
            'filters' => ['period' => $period, 'profile' => $profileSqid],
            'profiles' => ClubSportProfile::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', ClubEventDetails::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubEventDetails::class);

        return view('club.competitions._form_dialog', ['event' => null, 'competition' => null] + $this->formOptions());
    }

    public function store(SaveCompetitionRequest $request): RedirectResponse {
        Gate::authorize('create', ClubEventDetails::class);
        /** @var User $actor */
        $actor = Auth::user();
        try {
            $event = $this->competitions->create($this->currentOrganization(), $actor, $this->withUtcTimes($request->validated()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['room_id' => ErrorText::for($e)])->withInput();
        }

        return redirect()->route('club.competitions.show', $event)->with('success', __('club.competitions.flash.created'));
    }

    public function show(Event $event): View {
        $details = $this->clubDetails($event);
        Gate::authorize('view', $details);
        $competition = $this->competitions->detailsOf($event)->load('profile');
        $entries = ClubCompetitionEntry::query()->where('event_id', $event->id)->with(['member:id,first_name,last_name,member_no,birth_date'])->get()
            ->sortBy(fn(ClubCompetitionEntry $e): string => $e->discipline_code . ' ' . $e->member?->last_name)->values();
        $performances = ClubPerformance::query()->where('event_id', $event->id)->with(['member:id,first_name,last_name', 'confirmedBy:id,name'])->orderBy('discipline_code')->orderBy('placement')->get();
        $canManage = Gate::allows('manageParticipants', $details);

        return view('club.competitions.show', [
            'event' => $event->load(['clubGroups:id,name', 'rooms:id,name', 'responsibleUser:id,name']),
            'competition' => $competition,
            'details' => $details,
            'entries' => $entries,
            'performances' => $performances,
            'isCancelled' => $event->status === EventStatus::Cancelled,
            'canManage' => $canManage,
            'canEdit' => Gate::allows('update', $details),
            'canConfirm' => Gate::allows('create', ClubPerformance::class),
            'missingRangeOfficer' => $this->competitions->missingRangeOfficer($event),
            'reviewCount' => $entries->where('status', ClubEntryStatus::NeedsReview)->count(),
            'resourceBookings' => \App\Models\Club\ClubResourceBooking::query()->where('event_id', $event->id)->with(['resource:id,name,parent_id,kind', 'member:id,first_name,last_name'])->orderBy('starts_at')->get(),
            'missingClearances' => app(\App\Services\Club\ClubResourceService::class)->missingClearances($event),
        ]);
    }

    public function edit(Event $event): View {
        $details = $this->clubDetails($event);
        Gate::authorize('update', $details);

        return view('club.competitions._form_dialog', ['event' => $event->load(['clubGroups', 'rooms', 'responsibleUser']), 'competition' => $this->competitions->detailsOf($event)->load('profile')] + $this->formOptions());
    }

    public function update(SaveCompetitionRequest $request, Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('update', $details);
        /** @var User $actor */
        $actor = Auth::user();
        try {
            $this->competitions->update($event, $actor, $this->withUtcTimes($request->validated()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['room_id' => ErrorText::for($e)])->withInput();
        }

        return redirect()->route('club.competitions.show', $event)->with('success', __('club.competitions.flash.updated'));
    }

    public function entryDialog(Event $event): View {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        $competition = $this->competitions->detailsOf($event)->load('profile');

        return view('club.competitions._entry_dialog', [
            'event' => $event,
            'competition' => $competition,
            'members' => ClubMember::query()->current()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'member_no']),
        ]);
    }

    public function enter(SaveCompetitionEntryRequest $request, Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        $data = $request->validated();
        /** @var ClubMember $member */
        $member = ClubMember::query()->whereKey((int) $data['club_member_id'])->firstOrFail();
        /** @var User $actor */
        $actor = Auth::user();
        $entries = $this->competitions->enter($event, $member, array_values((array) $data['disciplines']), $actor, ClubParticipationSource::Leader, (bool) ($data['force'] ?? false));
        $review = $entries->contains(fn(ClubCompetitionEntry $e): bool => $e->status === ClubEntryStatus::NeedsReview);

        return redirect()->route('club.competitions.show', $event)->with($review ? 'warning' : 'success', __($review ? 'club.competitions.flash.entered_review' : 'club.competitions.flash.entered', ['name' => $member->fullName(), 'count' => $entries->count()]));
    }

    public function clearEntry(Request $request, Event $event, ClubCompetitionEntry $entry): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        abort_unless($entry->event_id === $event->id, 404);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->competitions->clearEntry($entry, $actor, $data['note'] ?? null);

        return redirect()->route('club.competitions.show', $event)->with('success', __('club.competitions.flash.entry_cleared'));
    }

    public function withdraw(Event $event, ClubCompetitionEntry $entry): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        abort_unless($entry->event_id === $event->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->competitions->withdraw($entry, $actor);

        return redirect()->route('club.competitions.show', $event)->with('success', __('club.competitions.flash.withdrawn'));
    }

    public function resultDialog(Event $event, ClubCompetitionEntry $entry): View {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        abort_unless($entry->event_id === $event->id, 404);
        $competition = $this->competitions->detailsOf($event)->load('profile');

        return view('club.competitions._result_dialog', ['event' => $event, 'entry' => $entry->load('member'), 'competition' => $competition, 'discipline' => $competition->discipline($entry->discipline_code)]);
    }

    public function recordResult(SavePerformanceRequest $request, Event $event, ClubCompetitionEntry $entry): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        abort_unless($entry->event_id === $event->id, 404);
        $competition = $this->competitions->detailsOf($event);
        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated() + ['club_sport_profile_id' => $competition->club_sport_profile_id, 'discipline_code' => $entry->discipline_code, 'event_id' => $event->id, 'performed_on' => CarbonImmutable::instance($event->started_at)->setTimezone(Tz::current())->toDateString()];
        $this->competitions->recordPerformance($entry->member()->firstOrFail(), $data, $actor);

        return redirect()->route('club.competitions.show', $event)->with('success', __('club.competitions.flash.result_saved'));
    }

    private function clubDetails(Event $event): ClubEventDetails {
        $details = $event->clubDetails;
        abort_if($details === null || $details->kind !== ClubEventKind::Competition, 404);

        return $details;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withUtcTimes(array $data): array {
        $tz = trim((string) ($data['timezone'] ?? ''));
        $tz = Tz::isValid($tz) && $tz !== 'UTC' ? $tz : Tz::current();
        $data['timezone'] = $tz;
        foreach (['started_at', 'ended_at'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = CarbonImmutable::parse((string) $data[$key], $tz)->utc()->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function formOptions(): array {
        return [
            'profiles' => ClubSportProfile::query()->where('is_active', true)->orderBy('name')->get(),
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'leaders' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name']),
            'formTz' => Tz::current(),
        ];
    }
}
