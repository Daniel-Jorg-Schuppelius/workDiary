<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMatchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubAvailabilityStatus, ClubEventKind, ClubEventRoleKind, ClubLineupSlot};
use App\Enums\Event\EventStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{SaveClubMatchRequest, SaveEventRoleRequest, SaveLineupRequest, SaveMatchResultRequest};
use App\Models\Club\{ClubEventDetails, ClubEventRole, ClubGroup, ClubMember, ClubSeason};
use App\Models\{Event, Room, User};
use App\Services\Club\{ClubMatchService, ClubTeamService};
use App\Services\UI\DateRangeContext;
use App\Support\{ErrorText, Sqid, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Spieltage (Feature 159, MVP-852): Liste, Anlage/Änderung, Verfügbarkeit,
 * Aufstellung mit Freigabe, Terminrollen und Ergebnis. Rechte laufen über
 * die Vereinstermin-Policy (Verwaltung oder Leitung der Mannschaft).
 */
class ClubMatchController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubMatchService $matches,
        private readonly ClubTeamService $teams,
    ) {}

    public function index(Request $request, DateRangeContext $range): View {
        Gate::authorize('viewAny', ClubEventDetails::class);
        /** @var User $user */
        $user = Auth::user();
        $period = (string) $request->query('period', 'upcoming');
        $teamSqid = trim((string) $request->query('team', ''));
        $seasonSqid = trim((string) $request->query('season', ''));
        $now = CarbonImmutable::now();

        $query = Event::query()
            ->whereHas('clubMatch')
            ->with(['clubMatch.team:id,name', 'clubMatch.season:id,name', 'clubGroups:id,name,leader_user_id', 'rooms:id,name'])
            ->withCount('clubLineupEntries as lineup_count');
        $teamId = $teamSqid !== '' ? Sqid::decodeOrNumeric(ClubGroup::class, $teamSqid) : null;
        if ($teamId !== null) {
            $query->whereHas('clubMatch', fn(Builder $q) => $q->where('club_group_id', $teamId));
        }
        $seasonId = $seasonSqid !== '' ? Sqid::decodeOrNumeric(ClubSeason::class, $seasonSqid) : null;
        if ($seasonId !== null) {
            $query->whereHas('clubMatch', fn(Builder $q) => $q->where('club_season_id', $seasonId));
        }
        if (! Gate::allows('viewRegister', ClubMember::class)) {
            $query->whereHas('clubGroups', fn(Builder $groups) => $groups->where('leader_user_id', $user->id));
        }
        if ($period === 'past') {
            $query->where('ended_at', '<', $now)->orderByDesc('started_at');
        } elseif ($period === 'range') {
            $current = $range->current();
            $query->where('started_at', '>=', CarbonImmutable::instance($current['from'])->startOfDay()->utc())
                ->where('started_at', '<', CarbonImmutable::instance($current['to'])->addDay()->startOfDay()->utc())
                ->orderBy('started_at');
        } else {
            $period = 'upcoming';
            $query->where('ended_at', '>=', $now)->orderBy('started_at');
        }

        return view('club.matches.index', [
            'events' => $query->paginate(30)->withQueryString(),
            'filters' => ['period' => $period, 'team' => $teamSqid, 'season' => $seasonSqid],
            'teams' => ClubGroup::query()->where('is_team', true)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'seasons' => ClubSeason::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'canManage' => Gate::allows('create', ClubEventDetails::class),
            'openProposals' => \App\Models\Club\ClubMatchProposal::query()->where('status', \App\Enums\Club\ClubProposalStatus::Open->value)->count(),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', ClubEventDetails::class);
        $teamId = Sqid::decodeOrNumeric(ClubGroup::class, (string) $request->query('team', ''));

        return view('club.matches._form_dialog', ['event' => null, 'match' => null, 'preselectedTeam' => $teamId !== null ? ClubGroup::query()->find($teamId) : null] + $this->formOptions());
    }

    public function store(SaveClubMatchRequest $request): RedirectResponse {
        Gate::authorize('create', ClubEventDetails::class);
        /** @var User $actor */
        $actor = Auth::user();
        try {
            $event = $this->matches->createMatch($this->currentOrganization(), $actor, $this->withUtcTimes($request->validated()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['room_id' => ErrorText::for($e)])->withInput();
        }

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.created'));
    }

    public function show(Event $event): View {
        $details = $this->clubDetails($event);
        Gate::authorize('view', $details);
        $match = $this->matches->detailsOf($event);
        $match->load(['team.department', 'season', 'lineupReleasedBy:id,name', 'resultRecordedBy:id,name']);
        $profile = $this->teams->profileFor($match->team()->firstOrFail());
        $candidates = $this->matches->candidatesFor($event);
        $canManage = Gate::allows('manageParticipants', $details);
        $conflicts = $canManage ? $this->matches->conflictsFor($event) : [];

        return view('club.matches.show', [
            'event' => $event->load(['clubGroups:id,name', 'rooms:id,name', 'responsibleUser:id,name']),
            'match' => $match,
            'details' => $details,
            'profile' => $profile,
            'candidates' => $candidates,
            'conflicts' => $conflicts,
            'roles' => ClubEventRole::query()->where('event_id', $event->id)->with(['member:id,first_name,last_name', 'user:id,name'])->orderBy('role')->get(),
            'lineupEntries' => \App\Models\Club\ClubLineupEntry::query()->where('event_id', $event->id)->with('member:id,first_name,last_name')->orderBy('order_no')->orderBy('id')->get(),
            'resourceBookings' => \App\Models\Club\ClubResourceBooking::query()->where('event_id', $event->id)->with(['resource:id,name,parent_id,kind', 'member:id,first_name,last_name'])->orderBy('starts_at')->get(),
            'missingClearances' => app(\App\Services\Club\ClubResourceService::class)->missingClearances($event),
            'isCancelled' => $event->status === EventStatus::Cancelled,
            'canManage' => $canManage,
            'canEdit' => Gate::allows('update', $details),
            'slots' => $profile?->hasPairings() ? ClubLineupSlot::cases() : [ClubLineupSlot::Field, ClubLineupSlot::Bench],
            'availabilityOptions' => ClubAvailabilityStatus::cases(),
        ]);
    }

    public function edit(Event $event): View {
        $details = $this->clubDetails($event);
        Gate::authorize('update', $details);

        return view('club.matches._form_dialog', ['event' => $event->load(['clubGroups', 'rooms', 'responsibleUser']), 'match' => $this->matches->detailsOf($event), 'preselectedTeam' => null] + $this->formOptions());
    }

    public function update(SaveClubMatchRequest $request, Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('update', $details);
        /** @var User $actor */
        $actor = Auth::user();
        try {
            $this->matches->updateMatch($event, $actor, $this->withUtcTimes($request->validated()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['room_id' => ErrorText::for($e)])->withInput();
        }

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.updated'));
    }

    /** Verfügbarkeit durch die Leitung (Mitglieder antworten im Portal). */
    public function availability(Request $request, Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        $data = $request->validate([
            'club_member_id' => ['required', 'string'],
            'status' => ['required', 'string', \Illuminate\Validation\Rule::enum(ClubAvailabilityStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $memberId = Sqid::decodeOrNumeric(ClubMember::class, (string) $data['club_member_id']);
        $member = $memberId !== null ? ClubMember::query()->find($memberId) : null;
        abort_if($member === null, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->matches->setAvailability($event, $member, ClubAvailabilityStatus::from((string) $data['status']), $actor, $data['note'] ?? null);

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.availability_saved'));
    }

    public function saveLineup(SaveLineupRequest $request, Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        /** @var User $actor */
        $actor = Auth::user();
        $this->matches->saveLineup($event, $actor, $request->rows());
        if ($request->boolean('release')) {
            try {
                $this->matches->releaseLineup($event, $actor);
            } catch (ValidationException $e) {
                return redirect()->route('club.matches.show', $event)->with('warning', __('club.matches.flash.lineup_saved_conflicts'))->withErrors($e->errors());
            }

            return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.lineup_released'));
        }

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.lineup_saved'));
    }

    public function releaseLineup(Request $request, Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        $data = $request->validate(['override' => ['sometimes', 'boolean'], 'note' => ['nullable', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->matches->releaseLineup($event, $actor, (bool) ($data['override'] ?? false), $data['note'] ?? null);

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.lineup_released'));
    }

    public function withdrawLineup(Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        /** @var User $actor */
        $actor = Auth::user();
        $this->matches->withdrawLineup($event, $actor);

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.lineup_withdrawn'));
    }

    public function roleDialog(Event $event): View {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);

        return view('club.matches._role_dialog', [
            'event' => $event,
            'roles' => ClubEventRoleKind::cases(),
            'members' => ClubMember::query()->current()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'member_no']),
            'users' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function assignRole(SaveEventRoleRequest $request, Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $this->matches->assignRole($event, ClubEventRoleKind::from((string) $data['role']), $data, $actor);

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.role_saved'));
    }

    public function removeRole(Event $event, ClubEventRole $role): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        abort_unless($role->event_id === $event->id, 404);
        /** @var User $actor */
        $actor = Auth::user();
        $this->matches->removeRole($role, $actor);

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.role_removed'));
    }

    public function resultDialog(Event $event): View {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        $match = $this->matches->detailsOf($event);

        return view('club.matches._result_dialog', [
            'event' => $event,
            'match' => $match,
            'format' => $this->resultFormatFor($match),
        ]);
    }

    public function recordResult(SaveMatchResultRequest $request, Event $event): RedirectResponse {
        $details = $this->clubDetails($event);
        Gate::authorize('manageParticipants', $details);
        /** @var User $actor */
        $actor = Auth::user();
        $this->matches->recordResult($event, $actor, $request->validated());

        return redirect()->route('club.matches.show', $event)->with('success', __('club.matches.flash.result_saved'));
    }

    private function resultFormatFor(\App\Models\Club\ClubMatchDetails $match): \App\Enums\Club\ClubResultFormat {
        $profile = $this->teams->profileFor($match->team()->firstOrFail());

        return $profile !== null ? $profile->result_format : \App\Enums\Club\ClubResultFormat::Goals;
    }

    private function clubDetails(Event $event): ClubEventDetails {
        $details = $event->clubDetails;
        abort_if($details === null || $details->kind !== ClubEventKind::Match, 404);

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
        foreach (['started_at', 'ended_at', 'meet_at'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $data[$key] = CarbonImmutable::parse((string) $data[$key], $tz)->utc()->format('Y-m-d H:i:s');
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function formOptions(): array {
        return [
            'teams' => ClubGroup::query()->where('is_team', true)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'leader_user_id']),
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'seasons' => ClubSeason::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'leaders' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name']),
            'formTz' => Tz::current(),
        ];
    }
}
