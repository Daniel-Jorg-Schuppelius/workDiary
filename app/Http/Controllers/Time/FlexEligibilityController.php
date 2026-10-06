<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexEligibilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Time;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Models\Time\FlexEligibility;
use App\Support\SortableQuery;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CRUD für die periodische Gleitzeit-Berechtigung eines Mitarbeiters.
 * Verwendet das gleiche Muster wie {@see WorkScheduleController}: pro
 * Mitarbeiter eine Sub-Seite mit allen Perioden, Anlegen via Inline-Form,
 * Beenden über das valid_to-Feld.
 */
class FlexEligibilityController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(Request $request, User $user): View {
        Gate::authorize('viewAny', [FlexEligibility::class, $user]);
        $this->ensureSameOrg($user);

        $query = FlexEligibility::query()->where('user_id', $user->id);
        [$sort, $dir] = SortableQuery::resolve($request, ['valid_from', 'valid_to', 'note'], 'valid_from', 'desc');
        if ($sort === 'valid_to') {
            // Offene Perioden zählen als „bis auf Weiteres" und stehen hinter jedem Enddatum.
            $query->orderByRaw($dir === 'asc' ? 'valid_to IS NULL asc' : 'valid_to IS NULL desc');
        }
        $periods = $query
            ->orderBy($sort, $dir)
            ->orderByDesc('valid_from')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('flex-eligibilities.index', [
            'member' => $user,
            'periods' => $periods,
            'sort' => $sort,
            'dir' => $dir,
            'isCurrentlyEligible' => $user->isFlexEligible(),
        ]);
    }

    public function store(User $user, Request $request): RedirectResponse {
        Gate::authorize('create', [FlexEligibility::class, $user]);
        $this->ensureSameOrg($user);

        $data = $request->validate([
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // Vermeide das Aufsplitten existierender offener Perioden ohne
        // explizite Aktion: wenn am gleichen valid_from bereits eine Periode
        // existiert, wird sie aktualisiert; sonst neu angelegt.
        FlexEligibility::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'valid_from' => $data['valid_from'],
            ],
            [
                'organization_id' => $this->currentOrganization()->id,
                'valid_to' => $data['valid_to'] ?? null,
                'note' => $data['note'] ?? null,
            ]
        );

        return redirect()->toList('users.flex-eligibility.index', [$user])
            ->with('success', __('flex.eligibility.flash.saved'));
    }

    public function update(User $user, FlexEligibility $eligibility, Request $request): RedirectResponse {
        Gate::authorize('delete', $eligibility); // Update braucht dieselbe Berechtigung wie Delete
        $this->ensureSameOrg($user);
        abort_unless($eligibility->user_id === $user->id, 404);

        $data = $request->validate([
            'valid_to' => ['nullable', 'date', 'after_or_equal:' . $eligibility->valid_from->toDateString()],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $eligibility->update($data);

        return redirect()->toList('users.flex-eligibility.index', [$user])
            ->with('success', __('flex.eligibility.flash.saved'));
    }

    public function destroy(User $user, FlexEligibility $eligibility): RedirectResponse {
        Gate::authorize('delete', $eligibility);
        $this->ensureSameOrg($user);
        abort_unless($eligibility->user_id === $user->id, 404);

        $eligibility->delete();

        return redirect()->toList('users.flex-eligibility.index', [$user])
            ->with('success', __('flex.eligibility.flash.deleted'));
    }

    private function ensureSameOrg(User $member): void {
        abort_unless($member->organization_id === $this->currentOrganization()->id, 403);
    }
}
