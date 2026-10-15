<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DispatchDayPlanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Schedule;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Diary\TourProposal;
use App\Models\Platform\{Organization, User};
use App\Services\Routing\TourProposalService;
use App\Services\Schedule\WorkWindowResolver;
use App\Support\{ErrorText, Sqid, Tz};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Tagesvorschlag der Leitstelle (MVP-1179): Fahrer und Aufträge eines Tages
 * in einem Lauf auf Touren verteilen; Übernahme legt die Touren an.
 */
class DispatchDayPlanController extends Controller {
    public function __construct(
        private readonly TourProposalService $proposals,
        private readonly WorkWindowResolver $workWindows,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::DispatchManage->value);
        /** @var User $auth */
        $auth = Auth::user();
        $organization = app('currentOrganization');
        $tz = $organization instanceof Organization ? Tz::ofOrganization($organization) : Tz::current();
        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = CarbonImmutable::parse((string) ($data['date'] ?? CarbonImmutable::today($tz)->toDateString()), $tz);

        $users = User::inCurrentOrganization()->orderBy('name')->get(['id', 'name', 'home_lat', 'home_lng']);
        // Vorbelegt: Fahrer mit Zuhause-Koordinaten oder einer Schicht an diesem Tag.
        $preselected = $users->filter(fn(User $user): bool => ($user->home_lat !== null && $user->home_lng !== null)
            || $this->workWindows->windowsFor($user, $date) !== [[WorkWindowResolver::DEFAULT_DAY_START, WorkWindowResolver::DEFAULT_DAY_END]])
            ->pluck('sqid')->all();
        $candidates = $this->proposals->dayCandidates($date);
        $recent = TourProposal::query()->whereNull('tour_id')->whereBetween('proposal_date', DateRange::days($date, $date))->latest('id')->limit(10)->get();

        return view('dispatch.day-plan', [
            'date' => $date,
            'users' => $users,
            'preselected' => old('drivers', $preselected),
            'candidates' => $candidates,
            'recent' => $recent,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(Permission::DispatchManage->value);
        /** @var User $auth */
        $auth = Auth::user();
        $organization = app('currentOrganization');
        abort_unless($organization instanceof Organization, 403);
        $tz = Tz::ofOrganization($organization);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'drivers' => ['required', 'array', 'min:1'],
            'drivers.*' => ['string', 'max:64'],
        ]);
        $date = CarbonImmutable::parse((string) $data['date'], $tz);
        $driverIds = User::inCurrentOrganization()
            ->whereIn('id', array_values(array_filter(array_map(static fn($sqid): ?int => Sqid::decodeOrNumeric(User::class, (string) $sqid), (array) $data['drivers']))))
            ->pluck('id')->map(static fn($id): int => (int) $id)->all();
        $entryIds = $this->proposals->dayCandidates($date)->pluck('id')->map(static fn($id): int => (int) $id)->all();

        try {
            $proposal = $this->proposals->requestForDay($organization, $date, array_values($driverIds), array_values($entryIds), $auth);
        } catch (RuntimeException $e) {
            return back()->withErrors(['drivers' => ErrorText::for($e)])->withInput();
        }

        return redirect()->route('tour-proposals.show', $proposal)->with('success', __('Vorschlag wird berechnet.'));
    }
}
