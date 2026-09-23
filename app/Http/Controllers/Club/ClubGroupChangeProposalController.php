<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGroupChangeProposalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Enums\Club\ClubProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\DecideClubProposalRequest;
use App\Models\Club\{ClubGroup, ClubGroupChangeProposal, ClubMember};
use App\Models\Platform\User;
use App\Services\Club\ClubGroupService;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Wechsel-/Prüfvorschläge (MVP-842): Liste der offenen Vorschläge aus dem
 * Kriterienabgleich, Entscheidung mit Wirksamkeitsdatum und optionaler
 * Zielgruppe. Gruppenleitung sieht nur Vorschläge ihrer Gruppen.
 */
class ClubGroupChangeProposalController extends Controller {
    public function __construct(
        private readonly ClubGroupService $groups,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ClubGroupChangeProposal::class);

        /** @var User $user */
        $user = Auth::user();
        $status = (string) $request->query('status', ClubProposalStatus::Open->value);

        $query = ClubGroupChangeProposal::query()
            ->with(['member', 'group:id,name,leader_user_id', 'suggestedGroup:id,name', 'decidedBy:id,name'])
            ->orderByDesc('created_at');

        if (ClubProposalStatus::tryFrom($status) instanceof ClubProposalStatus) {
            $query->where('status', $status);
        } else {
            $status = '';
        }
        if (! Gate::allows('viewRegister', ClubMember::class)) {
            $query->whereHas('group', fn($groups) => $groups->where('leader_user_id', $user->id));
        }

        return view('club.proposals.index', [
            'proposals' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'today' => CarbonImmutable::today(),
            'canRefresh' => Gate::allows('create', ClubGroup::class),
        ]);
    }

    public function decideDialog(ClubGroupChangeProposal $proposal): View {
        Gate::authorize('decide', $proposal);

        $proposal->load(['member', 'group:id,name,club_department_id', 'suggestedGroup:id,name']);
        $targets = ClubGroup::query()
            ->where('is_active', true)
            ->whereKeyNot($proposal->club_group_id)
            ->orderBy('name')
            ->get(['id', 'name', 'min_age', 'max_age']);

        return view('club.proposals._decide_dialog', [
            'proposal' => $proposal,
            'targets' => $targets,
            'today' => CarbonImmutable::today(),
            'canOverride' => Gate::allows('override', $proposal->group),
        ]);
    }

    public function confirm(DecideClubProposalRequest $request, ClubGroupChangeProposal $proposal): RedirectResponse {
        Gate::authorize('decide', $proposal);

        /** @var User $actor */
        $actor = Auth::user();
        $data = $request->validated();
        $target = filled($data['suggested_group_id'] ?? null)
            ? ClubGroup::query()->findOrFail((int) $data['suggested_group_id'])
            : null;
        $override = (bool) ($data['override'] ?? false);
        if ($override) {
            abort_unless(Gate::allows('override', $proposal->group), 403, (string) __('club.error.override_forbidden'));
        }

        $this->groups->confirmProposal($proposal, $actor, CarbonImmutable::parse((string) $data['effective_on']), $target, $override, $data['note'] ?? null);

        return redirect()
            ->toList('club.proposals.index')
            ->with('success', __('club.flash.proposal_confirmed'));
    }

    public function dismiss(Request $request, ClubGroupChangeProposal $proposal): RedirectResponse {
        Gate::authorize('decide', $proposal);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        /** @var User $actor */
        $actor = Auth::user();
        $this->groups->dismissProposal($proposal, $actor, $data['note'] ?? null);

        return redirect()
            ->toList('club.proposals.index')
            ->with('success', __('club.flash.proposal_dismissed'));
    }
}
