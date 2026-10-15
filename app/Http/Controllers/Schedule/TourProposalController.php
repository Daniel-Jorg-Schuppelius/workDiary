<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourProposalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Schedule;

use App\Enums\Tour\TourProposalStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Diary\{DiaryEntry, Tour, TourProposal};
use App\Models\Platform\User;
use App\Services\Routing\Solver\{TourPlan, TourProblem};
use App\Services\Routing\TourProposalService;
use App\Support\{ErrorText, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Tourvorschläge (MVP-1176): anfordern, ansehen, übernehmen, verwerfen —
 * für eine Tour (Recht an der Tour) oder einen Tag der Leitstelle
 * (`dispatch.manage`, MVP-1179); Rest-Neuplanung einer laufenden Tour (MVP-1180).
 */
class TourProposalController extends Controller {
    public function __construct(private readonly TourProposalService $proposals) {}

    public function store(Request $request, Tour $tour): RedirectResponse {
        Gate::authorize('update', $tour);
        /** @var User $auth */
        $auth = Auth::user();

        try {
            $proposal = $this->proposals->request($tour, $auth, $request->boolean('remainder'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['proposal' => ErrorText::for($e)]);
        }

        return redirect()->route('tour-proposals.show', $proposal)
            ->with('success', __('Vorschlag wird berechnet.'));
    }

    public function show(TourProposal $proposal): View {
        $this->authorizeProposal($proposal, 'view');
        $proposal->load(['tour.user:id,name', 'decider:id,name']);
        $tour = $proposal->tour;

        $timezone = (string) ($proposal->problem['timezone'] ?? ($tour?->organization !== null ? Tz::ofOrganization($tour->organization) : Tz::current()));
        $date = CarbonImmutable::parse((string) $proposal->proposal_date->toDateString(), $timezone);
        $clock = static fn(int $seconds): string => TourProblem::atSeconds($date, $timezone, $seconds)->setTimezone($timezone)->format('H:i');

        $plan = $proposal->status !== TourProposalStatus::Computing && $proposal->plan !== null ? TourPlan::fromArray($proposal->plan) : null;
        /** @var array<int, array<string, mixed>> $jobRows */
        $jobRows = array_values((array) ($proposal->problem['jobs'] ?? []));
        $jobs = collect($jobRows)->keyBy('id');
        /** @var array<int, array<string, mixed>> $vehicleRows */
        $vehicleRows = array_values((array) ($proposal->problem['vehicles'] ?? []));
        $vehicles = collect($vehicleRows)->keyBy('id');
        $entryIds = [];
        foreach ($plan !== null ? $plan->routes : [] as $route) {
            $entryIds = [...$entryIds, ...$route->jobIds()];
        }
        foreach ($plan !== null ? $plan->unassigned : [] as $unassignedJob) {
            $entryIds[] = $unassignedJob->jobId;
        }
        $entries = $entryIds !== [] ? DiaryEntry::query()->with('customer:id,name')->whereIn('id', $entryIds)->get()->keyBy('id') : collect();

        $routes = [];
        foreach ($plan !== null ? $plan->routes : [] as $route) {
            if ($route->stops === [] && $tour === null) {
                continue; // Tagesvorschlag: Fahrer ohne Stopps nicht als leere Tour zeigen
            }
            $stops = [];
            foreach ($route->stops as $i => $stop) {
                $job = $jobs->get($stop->jobId, []);
                $stops[] = [
                    'position' => $i + 1,
                    'entry' => $entries->get($stop->jobId),
                    'label' => (string) ($job['label'] ?? ('#' . $stop->jobId)),
                    'arrival' => $clock($stop->arrival),
                    'waiting' => intdiv($stop->waitingSeconds, 60),
                    'service_start' => $clock($stop->serviceStart),
                    'departure' => $clock($stop->departure),
                    'driving' => (int) ceil($stop->drivingSeconds / 60),
                    'distance_km' => round($stop->distanceMeters / 1000, 1),
                    'window' => ($job['window_start'] ?? null) !== null || ($job['window_end'] ?? null) !== null
                        ? (($job['window_start'] ?? null) !== null ? $clock((int) $job['window_start']) : '—') . '–' . (($job['window_end'] ?? null) !== null ? $clock((int) $job['window_end']) : '—')
                        : null,
                ];
            }
            $routes[] = [
                'label' => (string) ($vehicles->get($route->vehicleId)['label'] ?? ('#' . $route->vehicleId)),
                'stops' => $stops,
                'breaks' => array_map(static fn($b): array => ['at' => (string) ($jobs->get($b->beforeJobId)['label'] ?? '—'), 'start' => $clock($b->start), 'end' => $clock($b->end)], $route->breaks),
                'start' => $clock($route->startAt),
                'end' => $clock($route->endAt),
                'distance_km' => round($route->distanceMeters / 1000, 2),
                'driving_minutes' => (int) ceil($route->drivingSeconds / 60),
                'waiting_minutes' => (int) ceil($route->waitingSeconds / 60),
            ];
        }
        $unassigned = array_map(static fn($u): array => ['entry' => $entries->get($u->jobId), 'label' => (string) ($jobs->get($u->jobId)['label'] ?? ('#' . $u->jobId)), 'reason' => $u->reason], $plan !== null ? $plan->unassigned : []);

        return view('tours.proposals.show', [
            'proposal' => $proposal,
            'tour' => $tour,
            'routes' => $routes,
            'unassigned' => $unassigned,
            'metrics' => $proposal->metrics ?? ['before' => null, 'after' => []],
            'kind' => (string) ($proposal->problem['kind'] ?? TourProposalService::KIND_TOUR),
        ]);
    }

    public function adopt(TourProposal $proposal): RedirectResponse {
        $this->authorizeProposal($proposal, 'update');
        /** @var User $auth */
        $auth = Auth::user();
        try {
            $tours = $this->proposals->adopt($proposal, $auth);
        } catch (RuntimeException $e) {
            return back()->withErrors(['proposal' => ErrorText::for($e)]);
        }

        if ($proposal->tour_id !== null && isset($tours[0])) {
            return redirect()->route('tours.show', $tours[0])->with('success', __('Vorschlag übernommen.'));
        }

        return redirect()->route('dispatch.day-plan', ['date' => $proposal->proposal_date->toDateString()])
            ->with('success', trans_choice(':count Tour angelegt.|:count Touren angelegt.', count($tours), ['count' => count($tours)]));
    }

    public function rejectForm(TourProposal $proposal): View {
        $this->authorizeProposal($proposal, 'update');

        return view('tours.proposals._reject_dialog', ['proposal' => $proposal]);
    }

    public function reject(Request $request, TourProposal $proposal): RedirectResponse {
        $this->authorizeProposal($proposal, 'update');
        $data = $request->validate(['decision_note' => ['required', 'string', 'max:1000']]);
        /** @var User $auth */
        $auth = Auth::user();
        try {
            $this->proposals->reject($proposal, $auth, (string) $data['decision_note']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['proposal' => ErrorText::for($e)]);
        }

        if ($proposal->tour instanceof Tour) {
            return redirect()->route('tours.show', $proposal->tour)->with('success', __('Vorschlag verworfen.'));
        }

        return redirect()->route('dispatch.day-plan', ['date' => $proposal->proposal_date->toDateString()])->with('success', __('Vorschlag verworfen.'));
    }

    /** Tourvorschlag: Recht an der Tour; Tagesvorschlag: Dispositionsrecht. */
    private function authorizeProposal(TourProposal $proposal, string $ability): void {
        $tour = $proposal->tour;
        if ($tour instanceof Tour) {
            Gate::authorize($ability, $tour);

            return;
        }
        Gate::authorize(Permission::DispatchManage->value);
    }
}
