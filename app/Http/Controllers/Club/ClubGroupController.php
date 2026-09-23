<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\ClubGroupMembershipStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{AdmitClubGroupMemberRequest, DecideGroupMembershipRequest, EndGroupMembershipRequest, SaveClubGroupRequest};
use App\Models\Club\{ClubDepartment, ClubGroup, ClubGroupMembership, ClubMember, ClubSeason, ClubSportProfile};
use App\Models\User;
use App\Services\Club\{ClubGroupService, ClubTeamService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Gruppenregister des Vereins (MVP-842): Liste, Detailseite mit aktiven
 * Mitgliedern, Anträgen und offenen Vorschlägen, Aufnahme-/Beenden-Dialoge.
 * Gruppenleitung sieht nur eigene Gruppen; Ausnahmen nur mit club.manage.
 */
class ClubGroupController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubGroupService $groups,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClubGroup::class);

        /** @var User $user */
        $user = Auth::user();
        $q = trim((string) $request->query('q', ''));
        $departmentSqid = trim((string) $request->query('department', ''));
        $activeOnly = $request->query('active', '1') !== '0';

        $query = ClubGroup::query()
            ->with(['department:id,name', 'leader:id,name'])
            ->withCount(['activeMemberships', 'requestedMemberships', 'openProposals'])
            ->orderBy('name');

        if ($q !== '') {
            $query->whereLikeEscaped('name', $q);
        }
        $departmentId = $departmentSqid !== '' ? Sqid::decodeOrNumeric(ClubDepartment::class, $departmentSqid) : null;
        if ($departmentId !== null) {
            $query->where('club_department_id', $departmentId);
        }
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        if (! Gate::allows('viewRegister', ClubMember::class)) {
            $query->where('leader_user_id', $user->id);
        }

        return view('club.groups.index', [
            'groups' => $query->paginate(30)->withQueryString(),
            'filters' => ['q' => $q, 'department' => $departmentSqid, 'active' => $activeOnly],
            'departments' => ClubDepartment::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', ClubGroup::class),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubGroup::class);

        return view('club.groups._form_dialog', ['group' => null] + $this->formOptions());
    }

    public function store(SaveClubGroupRequest $request): RedirectResponse {
        Gate::authorize('create', ClubGroup::class);

        $group = $this->groups->createGroup($this->currentOrganization(), $request->validated());

        return redirect()
            ->route('club.groups.show', $group)
            ->with('success', __('club.flash.group_created'));
    }

    public function show(ClubGroup $group): View {
        Gate::authorize('view', $group);

        $today = CarbonImmutable::today();
        $group->load(['department:id,name', 'leader:id,name']);

        $active = $group->activeMemberships()
            ->with('member')
            ->where(fn(Builder $query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', \App\Support\Query\DateRange::day($today)))
            ->get()
            ->sortBy(fn(ClubGroupMembership $membership): string => $membership->member?->last_name . ' ' . $membership->member?->first_name)
            ->values();
        $requested = $group->requestedMemberships()->with('member')->orderBy('valid_from')->get();
        $ended = $group->memberships()->where('status', ClubGroupMembershipStatus::Ended->value)->count();
        $proposals = $group->openProposals()->with(['member', 'suggestedGroup:id,name'])->orderBy('created_at')->get();

        return view('club.groups.show', [
            'group' => $group,
            'active' => $active,
            'requested' => $requested,
            'endedCount' => $ended,
            'proposals' => $proposals,
            'today' => $today,
            'canManage' => Gate::allows('update', $group),
            'canDecide' => Gate::allows('decide', $group),
            'canOverride' => Gate::allows('override', $group),
        ] + $this->squadData($group, $today));
    }

    public function edit(ClubGroup $group): View {
        Gate::authorize('update', $group);

        return view('club.groups._form_dialog', ['group' => $group] + $this->formOptions());
    }

    public function update(SaveClubGroupRequest $request, ClubGroup $group): RedirectResponse {
        Gate::authorize('update', $group);

        $this->groups->updateGroup($group, $request->validated());

        return redirect()
            ->route('club.groups.show', $group)
            ->with('success', __('club.flash.group_updated'));
    }

    public function destroy(ClubGroup $group): RedirectResponse {
        Gate::authorize('delete', $group);

        $this->groups->deleteGroup($group);

        return redirect()
            ->toList('club.groups.index')
            ->with('success', __('club.flash.group_deleted'));
    }

    public function admitDialog(ClubGroup $group): View {
        Gate::authorize('decide', $group);

        $blocked = $group->memberships()
            ->whereIn('status', [ClubGroupMembershipStatus::Active->value, ClubGroupMembershipStatus::Requested->value])
            ->pluck('club_member_id');
        $candidates = ClubMember::query()
            ->current()
            ->whereNotIn('id', $blocked)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('club.groups._admit_dialog', [
            'group' => $group,
            'candidates' => $candidates,
            'today' => CarbonImmutable::today(),
            'canOverride' => Gate::allows('override', $group),
        ]);
    }

    public function admit(AdmitClubGroupMemberRequest $request, ClubGroup $group): RedirectResponse {
        Gate::authorize('decide', $group);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        /** @var ClubMember $member */
        $member = ClubMember::query()->findOrFail((int) $data['club_member_id']);
        $from = CarbonImmutable::parse((string) $data['valid_from']);
        $override = $this->override($group, (bool) ($data['override'] ?? false));

        if (($data['mode'] ?? 'admit') === 'request') {
            $this->groups->request($group, $member, $from, $actor, $data['note'] ?? null);
            $flash = __('club.flash.membership_requested');
        } else {
            $this->groups->admit($group, $member, $from, $actor, $override, $data['note'] ?? null);
            $flash = __('club.flash.member_admitted', ['name' => $member->fullName()]);
        }

        return redirect()->route('club.groups.show', $group)->with('success', $flash);
    }

    public function approveDialog(ClubGroup $group, ClubGroupMembership $membership): View {
        Gate::authorize('decide', $group);
        abort_unless($membership->club_group_id === $group->id, 404);
        $membership->load('member');
        $today = CarbonImmutable::today();
        $day = $membership->valid_from->greaterThan($today) ? CarbonImmutable::instance($membership->valid_from) : $today;
        /** @var ClubMember $member */
        $member = $membership->member;

        return view('club.groups._approve_dialog', [
            'group' => $group,
            'membership' => $membership,
            'today' => $today,
            'result' => $this->groups->evaluate($group, $member, $day),
            'canOverride' => Gate::allows('override', $group),
        ]);
    }

    public function approve(DecideGroupMembershipRequest $request, ClubGroup $group, ClubGroupMembership $membership): RedirectResponse {
        Gate::authorize('decide', $group);
        abort_unless($membership->club_group_id === $group->id, 404);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $this->groups->approve($membership, $actor, $this->override($group, (bool) ($data['override'] ?? false)), $data['note'] ?? null);

        return redirect()->route('club.groups.show', $group)->with('success', __('club.flash.membership_approved'));
    }

    public function reject(DecideGroupMembershipRequest $request, ClubGroup $group, ClubGroupMembership $membership): RedirectResponse {
        Gate::authorize('decide', $group);
        abort_unless($membership->club_group_id === $group->id, 404);

        /** @var User $actor */
        $actor = Auth::user();
        $this->groups->reject($membership, $actor, $request->validated()['note'] ?? null);

        return redirect()->route('club.groups.show', $group)->with('success', __('club.flash.membership_rejected'));
    }

    public function endDialog(ClubGroup $group, ClubGroupMembership $membership): View {
        Gate::authorize('decide', $group);
        abort_unless($membership->club_group_id === $group->id, 404);

        return view('club.groups._end_dialog', ['group' => $group, 'membership' => $membership->load('member'), 'today' => CarbonImmutable::today()]);
    }

    public function end(EndGroupMembershipRequest $request, ClubGroup $group, ClubGroupMembership $membership): RedirectResponse {
        Gate::authorize('decide', $group);
        abort_unless($membership->club_group_id === $group->id, 404);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $this->groups->end($membership, CarbonImmutable::parse((string) $data['valid_to']), $actor, $data['note'] ?? null);

        return redirect()->route('club.groups.show', $group)->with('success', __('club.flash.membership_ended'));
    }

    /** Kriterienabgleich auf Knopfdruck — derselbe Lauf wie der tägliche Scan, für die ganze Organisation. */
    public function refreshProposals(): RedirectResponse {
        Gate::authorize('viewAny', ClubGroup::class);
        abort_unless(Gate::allows('create', ClubGroup::class) || Gate::allows('viewAny', \App\Models\Club\ClubGroupChangeProposal::class), 403);

        $created = $this->groups->refreshProposals($this->currentOrganization());

        return redirect()
            ->toList('club.proposals.index')
            ->with('success', trans_choice('club.flash.proposals_refreshed', $created, ['count' => $created]));
    }

    /** Ausnahme nur, wenn gewünscht UND erlaubt — ohne Recht wird sie abgewiesen, nicht still ignoriert. */
    private function override(ClubGroup $group, bool $requested): bool {
        if (! $requested) {
            return false;
        }
        abort_unless(Gate::allows('override', $group), 403, (string) __('club.error.override_forbidden'));

        return true;
    }

    /** @return array{departments: Collection<int, ClubDepartment>, leaders: Collection<int, User>} */
    private function formOptions(): array {
        return [
            'departments' => ClubDepartment::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'leaders' => User::query()->inCurrentOrganization()->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            // Gradkriterien (MVP-846): Ordnungen mit ihren Graden.
            'gradingSystems' => \App\Models\Club\ClubGradingSystem::query()->where('is_active', true)->with('grades')->orderBy('name')->get(),
            // Sportartenprofile (MVP-852) für Mannschaften.
            'profiles' => ClubSportProfile::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Saisonkader der Mannschaft (MVP-852): gewählte Saison (Query `season`), sonst die laufende, sonst die jüngste.
     *
     * @return array<string, mixed>
     */
    private function squadData(ClubGroup $group, CarbonImmutable $today): array {
        if (! $group->is_team) {
            return ['seasons' => collect(), 'season' => null, 'squad' => null, 'squadMembers' => collect(), 'profile' => null];
        }
        $teams = app(ClubTeamService::class);
        $seasons = ClubSeason::query()->orderByDesc('starts_on')->get();
        $seasonId = Sqid::decodeOrNumeric(ClubSeason::class, (string) request()->query('season', ''));
        $season = ($seasonId !== null ? $seasons->firstWhere('id', $seasonId) : null)
            ?? $seasons->first(fn(ClubSeason $s): bool => $s->contains($today))
            ?? $seasons->first();
        $squad = $season !== null ? $teams->squadFor($group, $season, false) : null;

        return [
            'seasons' => $seasons,
            'season' => $season,
            'squad' => $squad,
            'squadMembers' => $squad !== null ? $squad->members()->with('member')->orderByRaw('CASE WHEN valid_to IS NULL THEN 0 ELSE 1 END')->orderBy('strength_rank')->orderBy('jersey_no')->get() : collect(),
            'profile' => $teams->profileFor($group),
        ];
    }
}
