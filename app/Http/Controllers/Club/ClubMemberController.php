<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\ClubMembershipKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{ChangeClubMembershipKindRequest, LeaveClubMemberRequest, SaveClubMemberRequest};
use App\Models\Club\{ClubGroup, ClubMember};
use App\Models\User;
use App\Services\Club\ClubMemberService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Mitgliederregister des Vereins (Feature 159, MVP-842): Voll-Höhe-Liste
 * mit Filtern, Detailseite mit Verlauf, Gruppen und Vertretungen, Dialoge
 * für Stammdaten, Art-Wechsel und Austritt. Fachlogik im ClubMemberService.
 */
class ClubMemberController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubMemberService $members,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClubMember::class);

        /** @var User $user */
        $user = Auth::user();
        $q = trim((string) $request->query('q', ''));
        $kind = (string) $request->query('kind', '');
        $status = (string) $request->query('status', 'current');
        $groupSqid = trim((string) $request->query('group', ''));

        $query = ClubMember::query()
            ->with(['activeGroupMemberships.group:id,name'])
            ->withCount('activeGroupMemberships')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($q !== '') {
            $query->search($q);
        }
        if (ClubMembershipKind::tryFrom($kind) instanceof ClubMembershipKind) {
            $query->where('kind', $kind);
        }
        match ($status) {
            'left' => $query->left(),
            'all' => null,
            default => $query->current(),
        };
        $groupId = $groupSqid !== '' ? Sqid::decodeOrNumeric(ClubGroup::class, $groupSqid) : null;
        if ($groupId !== null) {
            $query->whereHas('activeGroupMemberships', fn(Builder $memberships) => $memberships->where('club_group_id', $groupId));
        }

        // Gruppenleitung ohne Registerrecht sieht ausschließlich Mitglieder ihrer Gruppen.
        if (! Gate::allows('viewRegister', ClubMember::class)) {
            $query->whereHas('activeGroupMemberships.group', fn(Builder $groups) => $groups->where('leader_user_id', $user->id));
        }

        return view('club.members.index', [
            'members' => $query->paginate(30)->withQueryString(),
            'filters' => ['q' => $q, 'kind' => $kind, 'status' => $status === 'left' || $status === 'all' ? $status : 'current', 'group' => $groupSqid],
            'groups' => ClubGroup::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', ClubMember::class),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ClubMember::class);

        return view('club.members._form_dialog', ['member' => null, 'users' => $this->linkableUsers()]);
    }

    public function store(SaveClubMemberRequest $request): RedirectResponse {
        Gate::authorize('create', ClubMember::class);

        /** @var User $actor */
        $actor = Auth::user();
        $member = $this->members->create($this->currentOrganization(), $actor, $request->validated());

        return redirect()
            ->route('club.members.show', $member)
            ->with('success', __('club.flash.member_created', ['no' => $member->displayNo()]));
    }

    public function show(ClubMember $member): View {
        Gate::authorize('view', $member);

        $member->load([
            'user:id,name',
            'periods',
            'groupMemberships' => fn($query) => $query->with(['group:id,name,club_department_id', 'decidedBy:id,name'])->orderByDesc('valid_from'),
            'guardians' => fn($query) => $query->with('user:id,name')->orderBy('revoked_at')->orderBy('name'),
            'proposals' => fn($query) => $query->where('status', \App\Enums\Club\ClubProposalStatus::Open->value)->with(['group:id,name', 'suggestedGroup:id,name']),
        ]);

        return view('club.members.show', [
            'member' => $member,
            'canManage' => Gate::allows('update', $member),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function edit(ClubMember $member): View {
        Gate::authorize('update', $member);

        return view('club.members._form_dialog', ['member' => $member, 'users' => $this->linkableUsers()]);
    }

    public function update(SaveClubMemberRequest $request, ClubMember $member): RedirectResponse {
        Gate::authorize('update', $member);

        $this->members->update($member, $request->validated());

        return redirect()
            ->route('club.members.show', $member)
            ->with('success', __('club.flash.member_updated'));
    }

    public function kindDialog(ClubMember $member): View {
        Gate::authorize('manageMembership', $member);

        return view('club.members._kind_dialog', ['member' => $member, 'today' => CarbonImmutable::today()]);
    }

    public function changeKind(ChangeClubMembershipKindRequest $request, ClubMember $member): RedirectResponse {
        Gate::authorize('manageMembership', $member);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $this->members->changeKind(
            $member,
            ClubMembershipKind::from((string) $data['kind']),
            CarbonImmutable::parse((string) $data['effective_on']),
            $actor,
            $data['note'] ?? null,
        );

        return redirect()
            ->route('club.members.show', $member)
            ->with('success', __('club.flash.kind_changed'));
    }

    public function leaveDialog(ClubMember $member): View {
        Gate::authorize('manageMembership', $member);

        return view('club.members._leave_dialog', ['member' => $member, 'today' => CarbonImmutable::today()]);
    }

    public function leave(LeaveClubMemberRequest $request, ClubMember $member): RedirectResponse {
        Gate::authorize('manageMembership', $member);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $this->members->leave($member, CarbonImmutable::parse((string) $data['left_on']), $actor, $data['note'] ?? null);

        return redirect()
            ->route('club.members.show', $member)
            ->with('success', __('club.flash.member_left'));
    }

    public function destroy(ClubMember $member): RedirectResponse {
        Gate::authorize('delete', $member);

        $member->delete();

        return redirect()
            ->toList('club.members.index')
            ->with('success', __('club.flash.member_deleted'));
    }

    /**
     * Verknüpfbare Konten: aktive Benutzer derselben Organisation.
     *
     * @return Collection<int, User>
     */
    private function linkableUsers(): Collection {
        return User::query()
            ->inCurrentOrganization()
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
