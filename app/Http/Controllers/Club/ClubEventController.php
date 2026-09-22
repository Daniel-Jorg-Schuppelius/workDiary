<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubEventController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\{ClubEventKind, ClubParticipationSource, ClubParticipationStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{CancelClubEventRequest, RegisterClubEventMemberRequest, SaveClubEventRequest};
use App\Models\Club\{ClubDepartment, ClubEventDetails, ClubEventParticipation, ClubGroup, ClubMember};
use App\Models\{Event, Room, User};
use App\Services\Club\ClubEventService;
use App\Services\UI\DateRangeContext;
use App\Support\{ErrorText, Sqid, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Vereinstermine (Feature 159, MVP-843): Liste der Termine mit Vereinsdetails,
 * Detailseite mit Soll-Liste, Anmeldungen und Warteliste, Dialoge für Termin,
 * Absage und Anmeldung. Der Termin bleibt ein Event des Kalenders (028);
 * Gruppenleitung sieht nur Termine ihrer Zielgruppen.
 */
class ClubEventController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubEventService $clubEvents,
    ) {}

    public function index(Request $request, DateRangeContext $range): View {
        Gate::authorize('viewAny', ClubEventDetails::class);

        /** @var User $user */
        $user = Auth::user();
        $period = (string) $request->query('period', 'upcoming');
        $kind = (string) $request->query('kind', '');
        $groupSqid = trim((string) $request->query('group', ''));
        $now = CarbonImmutable::now();

        $query = Event::query()
            ->whereHas('clubDetails')
            ->with(['clubDetails', 'clubGroups:id,name', 'responsibleUser:id,name', 'rooms:id,name'])
            ->withCount([
                'clubParticipations as registered_count' => fn(Builder $q) => $q->where('status', ClubParticipationStatus::Registered->value),
                'clubParticipations as waitlisted_count' => fn(Builder $q) => $q->where('status', ClubParticipationStatus::Waitlisted->value),
            ]);

        if (ClubEventKind::tryFrom($kind) instanceof ClubEventKind) {
            $query->whereHas('clubDetails', fn(Builder $details) => $details->where('kind', $kind));
        }
        $groupId = $groupSqid !== '' ? Sqid::decodeOrNumeric(ClubGroup::class, $groupSqid) : null;
        if ($groupId !== null) {
            $query->whereHas('clubGroups', fn(Builder $groups) => $groups->where('club_groups.id', $groupId));
        }
        if (! Gate::allows('viewRegister', ClubMember::class)) {
            $query->whereHas('clubGroups', fn(Builder $groups) => $groups->where('leader_user_id', $user->id));
        }

        if ($period === 'past') {
            $query->where('ended_at', '<', $now)->orderByDesc('started_at');
        } elseif ($period === 'range') {
            $current = $range->current();
            $query
                ->where('started_at', '>=', CarbonImmutable::instance($current['from'])->startOfDay()->utc())
                ->where('started_at', '<', CarbonImmutable::instance($current['to'])->addDay()->startOfDay()->utc())
                ->orderBy('started_at');
        } else {
            $period = 'upcoming';
            $query->where('ended_at', '>=', $now)->orderBy('started_at');
        }

        return view('club.events.index', [
            'events' => $query->paginate(30)->withQueryString(),
            'filters' => ['period' => $period, 'kind' => $kind, 'group' => $groupSqid],
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', ClubEventDetails::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubEventDetails::class);

        return view('club.events._form_dialog', ['event' => null] + $this->formOptions());
    }

    public function store(SaveClubEventRequest $request): RedirectResponse {
        Gate::authorize('create', ClubEventDetails::class);

        /** @var User $actor */
        $actor = Auth::user();
        try {
            $event = $this->clubEvents->create($this->currentOrganization(), $actor, $this->withUtcTimes($request->validated()));
        } catch (RuntimeException $e) {
            return back()->withErrors(['room_id' => ErrorText::for($e)])->withInput();
        }

        return redirect()
            ->route('club.events.show', $event)
            ->with('success', __('club.events.flash.created'));
    }

    public function show(Event $event): View {
        $details = $this->detailsOrFail($event);
        Gate::authorize('view', $details);

        $event->load(['clubGroups:id,name,leader_user_id', 'responsibleUser:id,name', 'rooms:id,name', 'series:id,title']);
        $participations = ClubEventParticipation::query()
            ->where('event_id', $event->id)
            ->with(['member', 'registeredBy:id,name', 'guardian:id,name'])
            ->orderBy('registered_at')
            ->orderBy('id')
            ->get();
        $byMember = $participations->keyBy('club_member_id');
        $targets = $this->clubEvents->targetMembers($event);

        return view('club.events.show', [
            'event' => $event,
            'details' => $details,
            'registered' => $participations->where('status', ClubParticipationStatus::Registered)->values(),
            'waitlisted' => $participations->where('status', ClubParticipationStatus::Waitlisted)->values(),
            'invited' => $participations->where('status', ClubParticipationStatus::Invited)->values(),
            'cancelledCount' => $participations->where('status', ClubParticipationStatus::Cancelled)->count(),
            'targets' => $targets,
            'byMember' => $byMember,
            'seats' => $this->clubEvents->seatSummary($event),
            'occurrenceCount' => $event->series_id === null ? $event->occurrences()->count() : null,
            'canManage' => Gate::allows('update', $details),
            'canParticipants' => Gate::allows('manageParticipants', $details),
            'isCancelled' => $event->cancelled_at !== null,
        ]);
    }

    public function edit(Event $event): View {
        $details = $this->detailsOrFail($event);
        Gate::authorize('update', $details);
        $event->load(['clubGroups:id', 'rooms:id']);

        return view('club.events._form_dialog', ['event' => $event, 'details' => $details] + $this->formOptions());
    }

    public function update(SaveClubEventRequest $request, Event $event): RedirectResponse {
        $details = $this->detailsOrFail($event);
        Gate::authorize('update', $details);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $this->withUtcTimes($request->validated());
        try {
            $this->clubEvents->update($event, $actor, $data, ($data['scope'] ?? 'this') === 'future');
        } catch (RuntimeException $e) {
            return back()->withErrors(['room_id' => ErrorText::for($e)])->withInput();
        }

        return redirect()
            ->route('club.events.show', $event)
            ->with('success', __('club.events.flash.updated'));
    }

    public function cancelDialog(Event $event): View {
        $details = $this->detailsOrFail($event);
        Gate::authorize('update', $details);

        return view('club.events._cancel_dialog', ['event' => $event, 'inSeries' => $event->series_id !== null || $event->recurrence_rule !== null]);
    }

    public function cancel(CancelClubEventRequest $request, Event $event): RedirectResponse {
        $details = $this->detailsOrFail($event);
        Gate::authorize('update', $details);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $this->clubEvents->cancel($event, $actor, $data['cancel_reason'] ?? null, ($data['scope'] ?? 'this') === 'future');

        return redirect()
            ->route('club.events.show', $event)
            ->with('success', __('club.events.flash.cancelled'));
    }

    public function registerDialog(Event $event): View {
        $details = $this->detailsOrFail($event);
        Gate::authorize('manageParticipants', $details);

        $active = ClubEventParticipation::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [ClubParticipationStatus::Registered->value, ClubParticipationStatus::Waitlisted->value])
            ->pluck('club_member_id');
        $targets = $this->clubEvents->targetMembers($event)->whereNotIn('id', $active)->values();
        $others = ClubMember::query()->current()->whereNotIn('id', $active)->whereNotIn('id', $targets->pluck('id'))->orderBy('last_name')->orderBy('first_name')->get();

        return view('club.events._register_dialog', [
            'event' => $event,
            'details' => $details,
            'targets' => $targets,
            'others' => $others,
            'seats' => $this->clubEvents->seatSummary($event),
        ]);
    }

    public function register(RegisterClubEventMemberRequest $request, Event $event): RedirectResponse {
        $details = $this->detailsOrFail($event);
        Gate::authorize('manageParticipants', $details);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        /** @var ClubMember $member */
        $member = ClubMember::query()->findOrFail((int) $data['club_member_id']);

        if (($data['mode'] ?? 'register') === 'invite') {
            $this->clubEvents->invite($event, $member, $actor);

            return redirect()->route('club.events.show', $event)->with('success', __('club.events.flash.invited', ['name' => $member->fullName()]));
        }

        $spontaneous = (bool) ($data['spontaneous'] ?? false);
        $source = $spontaneous
            ? ClubParticipationSource::Spontaneous
            : (Gate::allows('update', $details) ? ClubParticipationSource::Admin : ClubParticipationSource::Leader);
        $participation = $this->clubEvents->register($event, $member, $actor, $source, null, $spontaneous, $data['note'] ?? null);

        return redirect()
            ->route('club.events.show', $event)
            ->with('success', $participation->status === ClubParticipationStatus::Registered
                ? __('club.events.flash.registered', ['name' => $member->fullName()])
                : __('club.events.flash.waitlisted', ['name' => $member->fullName()]));
    }

    public function cancelRegistration(Request $request, Event $event, ClubEventParticipation $participation): RedirectResponse {
        $details = $this->detailsOrFail($event);
        Gate::authorize('manageParticipants', $details);
        abort_unless($participation->event_id === $event->id, 404);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        /** @var ClubMember $member */
        $member = $participation->member()->firstOrFail();
        $this->clubEvents->cancelRegistration($event, $member, $actor, true, $data['note'] ?? null);

        return redirect()
            ->route('club.events.show', $event)
            ->with('success', __('club.events.flash.registration_cancelled', ['name' => $member->fullName()]));
    }

    private function detailsOrFail(Event $event): ClubEventDetails {
        $details = $event->clubDetails()->first();
        abort_unless($details instanceof ClubEventDetails, 404);

        return $details;
    }

    /**
     * Wandzeit der Formularzeitzone → UTC (wie EventController, MVP-823).
     *
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
            'departments' => ClubDepartment::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'leaders' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->orderBy('name')->get(['id', 'name']),
            'formTz' => Tz::current(),
        ];
    }
}
