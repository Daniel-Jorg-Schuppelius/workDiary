<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubSportProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Club;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\SaveSportProfileRequest;
use App\Models\Club\ClubSportProfile;
use App\Models\User;
use App\Services\Club\{ClubStarterPackService, ClubTeamService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Sportartenprofile (Feature 159, MVP-852): Sportart als Konfiguration je Organisation. */
class ClubSportProfileController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ClubTeamService $teams,
        private readonly ClubStarterPackService $packs,
    ) {}

    public function index(): View {
        Gate::authorize('viewAny', ClubSportProfile::class);

        return view('club.profiles.index', [
            'profiles' => ClubSportProfile::query()->withCount(['groups', 'departments'])->orderBy('name')->get(),
            'canManage' => Gate::allows('create', ClubSportProfile::class),
            'packs' => $this->packs->available(),
        ]);
    }

    /** Startpaket einer Sportart anlegen (MVP-848): Profil, Abteilung, Gruppen, Sportstätten und sportartspezifische Bausteine. */
    public function installPack(Request $request): RedirectResponse {
        Gate::authorize('create', ClubSportProfile::class);
        $data = $request->validate(['pack' => ['required', 'string', 'max:40']]);
        /** @var User $user */
        $user = $request->user();
        $result = $this->packs->install($this->currentOrganization(), (string) $data['pack'], $user);

        return redirect()->route('club.profiles.index')->with('success', __('club.teams.flash.pack_installed', [
            'name' => $result['profile']->name,
            'groups' => $result['groups'],
            'resources' => $result['resources'],
        ]));
    }

    public function create(): View {
        Gate::authorize('create', ClubSportProfile::class);

        return view('club.profiles._form_dialog', ['profile' => null]);
    }

    public function store(SaveSportProfileRequest $request): RedirectResponse {
        Gate::authorize('create', ClubSportProfile::class);
        $this->teams->createProfile($this->currentOrganization(), $request->validated());

        return redirect()->route('club.profiles.index')->with('success', __('club.teams.flash.profile_saved'));
    }

    public function edit(ClubSportProfile $profile): View {
        Gate::authorize('update', $profile);

        return view('club.profiles._form_dialog', ['profile' => $profile]);
    }

    public function update(SaveSportProfileRequest $request, ClubSportProfile $profile): RedirectResponse {
        Gate::authorize('update', $profile);
        $this->teams->updateProfile($profile, $request->validated());

        return redirect()->route('club.profiles.index')->with('success', __('club.teams.flash.profile_saved'));
    }

    public function destroy(ClubSportProfile $profile): RedirectResponse {
        Gate::authorize('delete', $profile);
        $this->teams->deleteProfile($profile);

        return redirect()->route('club.profiles.index')->with('success', __('club.teams.flash.profile_deleted'));
    }
}
