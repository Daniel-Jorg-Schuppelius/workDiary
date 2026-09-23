<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubTeamController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\{SaveSeasonRequest, SaveSquadMemberRequest};
use App\Models\Club\{ClubGroup, ClubMember, ClubSeason, ClubSquad, ClubSquadMember};
use App\Models\User;
use App\Services\Club\ClubTeamService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/** Saisons und Saisonkader einer Mannschaft (MVP-852); Dialoge auf der Gruppenseite. */
class ClubTeamController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubTeamService $teams,
    ) {}

    public function createSeason(): View {
        Gate::authorize('create', ClubGroup::class);

        return view('club.groups._season_dialog', ['latest' => ClubSeason::query()->orderByDesc('ends_on')->first()]);
    }

    public function storeSeason(SaveSeasonRequest $request): RedirectResponse {
        Gate::authorize('create', ClubGroup::class);
        $season = $this->teams->createSeason($this->currentOrganization(), $request->validated());
        $back = trim((string) $request->input('return_to', ''));

        return redirect($back !== '' && str_starts_with($back, url('/')) ? $back : route('club.groups.index'))
            ->with('success', __('club.teams.flash.season_saved', ['name' => $season->name]));
    }

    public function createSquadMember(Request $request, ClubGroup $group): View {
        Gate::authorize('decide', $group);
        $squad = $this->squadFromRequest($request, $group);

        return view('club.groups._squad_member_dialog', [
            'group' => $group,
            'squad' => $squad,
            'entry' => null,
            'profile' => $this->teams->profileFor($group),
            'members' => ClubMember::query()->current()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'member_no', 'kind']),
        ]);
    }

    public function storeSquadMember(SaveSquadMemberRequest $request, ClubGroup $group): RedirectResponse {
        Gate::authorize('decide', $group);
        $squad = $this->squadFromRequest($request, $group);
        /** @var User $actor */
        $actor = Auth::user();
        $this->teams->addSquadMember($squad, $request->validated(), $actor);

        return redirect()->route('club.groups.show', [$group, 'season' => $squad->season?->sqid])->with('success', __('club.teams.flash.squad_member_saved'));
    }

    public function editSquadMember(ClubGroup $group, ClubSquadMember $squadMember): View {
        Gate::authorize('decide', $group);
        $squad = $this->squadOf($group, $squadMember);

        return view('club.groups._squad_member_dialog', [
            'group' => $group,
            'squad' => $squad,
            'entry' => $squadMember->load('member'),
            'profile' => $this->teams->profileFor($group),
            'members' => collect(),
        ]);
    }

    public function updateSquadMember(SaveSquadMemberRequest $request, ClubGroup $group, ClubSquadMember $squadMember): RedirectResponse {
        Gate::authorize('decide', $group);
        $squad = $this->squadOf($group, $squadMember);
        $this->teams->updateSquadMember($squadMember, $request->validated());

        return redirect()->route('club.groups.show', [$group, 'season' => $squad->season?->sqid])->with('success', __('club.teams.flash.squad_member_saved'));
    }

    public function endSquadMember(Request $request, ClubGroup $group, ClubSquadMember $squadMember): RedirectResponse {
        Gate::authorize('decide', $group);
        $squad = $this->squadOf($group, $squadMember);
        $data = $request->validate(['valid_to' => ['nullable', 'date']]);
        /** @var User $actor */
        $actor = Auth::user();
        $on = isset($data['valid_to']) ? CarbonImmutable::parse((string) $data['valid_to']) : CarbonImmutable::today();
        $this->teams->endSquadMember($squadMember, $on, $actor);

        return redirect()->route('club.groups.show', [$group, 'season' => $squad->season?->sqid])->with('success', __('club.teams.flash.squad_member_ended'));
    }

    /** Kader der Zuordnung — muss zur Mannschaft gehören. */
    private function squadOf(ClubGroup $group, ClubSquadMember $squadMember): ClubSquad {
        $squad = $squadMember->squad()->with('season')->first();
        abort_if($squad === null || $squad->club_group_id !== $group->id, 404);

        return $squad;
    }

    /** Kader der gewählten Saison (Query `season`), sonst der laufenden; legt den Kader bei Bedarf an. */
    private function squadFromRequest(Request $request, ClubGroup $group): ClubSquad {
        $seasonId = Sqid::decodeOrNumeric(ClubSeason::class, (string) $request->input('season', $request->query('season', '')));
        $season = $seasonId !== null ? ClubSeason::query()->find($seasonId) : $this->teams->seasonContaining($group->organization_id, CarbonImmutable::today());
        abort_if($season === null, 422, (string) __('club.teams.error.no_season'));
        $squad = $this->teams->squadFor($group, $season, true);
        abort_if($squad === null, 404);

        return $squad;
    }
}
