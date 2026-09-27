<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectorTourController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetCompliance;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\AssetCompliance\{AssetComplianceProfile, AssetInspectionSchedule};
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\AssetCompliance\Contracts\InspectionTourPlanner;
use App\Services\AssetCompliance\InspectorTourService;
use App\Support\{ErrorText, Sqid, Tz};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/** Prüfertouren (MVP-918): fällige Prüftermine eines Prüfers auswählen und als Tour planen. */
class InspectorTourController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly InspectorTourService $tours) {}

    public function index(Request $request, InspectionTourPlanner $planner): View {
        Gate::authorize('create', AssetComplianceProfile::class);

        $users = User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']);
        $inspectorId = Sqid::decodeOrNumeric(User::class, $request->string('inspector')->toString());
        $inspector = $users->firstWhere('id', $inspectorId) ?? $users->firstWhere('id', $request->user()?->id);
        $until = $this->date($request->string('until')->toString()) ?? CarbonImmutable::parse(Tz::now()->addDays(14)->toDateString());
        $date = $this->date($request->string('date')->toString()) ?? CarbonImmutable::parse(Tz::now()->addDay()->toDateString());

        return view('asset-compliance.tour', [
            'users' => $users,
            'inspector' => $inspector,
            'until' => $until,
            'date' => $date,
            'schedules' => $inspector instanceof User ? $this->tours->dueFor($inspector, $until) : collect(),
            'available' => $planner->available($this->currentOrganization()),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);

        $request->merge([
            'inspector_user_id' => Sqid::decodeOrNumeric(User::class, $request->string('inspector_user_id')->toString()),
            'schedule_ids' => array_map(
                static fn (mixed $id): ?int => Sqid::decodeOrNumeric(AssetInspectionSchedule::class, is_scalar($id) ? (string) $id : null),
                (array) $request->input('schedule_ids', []),
            ),
        ]);
        $data = $request->validate([
            'inspector_user_id' => ['required', 'integer', new ExistsInCurrentOrganization('users')],
            'date' => ['required', 'date'],
            'until' => ['required', 'date'],
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer'],
        ]);
        $inspector = User::inCurrentOrganization()->whereKey($data['inspector_user_id'])->firstOrFail();

        try {
            $tour = $this->tours->plan($inspector, CarbonImmutable::parse((string) $data['date'])->startOfDay(), CarbonImmutable::parse((string) $data['until'])->startOfDay(), array_values(array_map('intval', $data['schedule_ids'])));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', ErrorText::for($e));
        }

        return redirect()->route('tours.show', $tour)->with('success', __('inspection_tour.planned', ['count' => count($data['schedule_ids'])]));
    }

    /** Kalenderdatum ohne Zeitzone, wie das Tourdatum der Tourenplanung. */
    private function date(string $raw): ?CarbonImmutable {
        if ($raw === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($raw)->startOfDay();
        } catch (\Carbon\Exceptions\InvalidFormatException) {
            return null;
        }
    }
}
