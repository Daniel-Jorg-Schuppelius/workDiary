<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DutyPlanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Shift\{DutyPlanPeriodType, DutyPlanStatus};
use App\Models\DutyPlan;
use App\Support\{Setting, SortableQuery};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DutyPlanController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', DutyPlan::class);

        $status = $request->query('status', '');
        $period = $request->query('period', '');

        $query = DutyPlan::query()
            ->withCount('shifts');

        if ($status && in_array($status, DutyPlanStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($period && in_array($period, DutyPlanPeriodType::values(), true)) {
            $query->where('period_type', $period);
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'name' => 'name',
            'period_type' => 'period_type',
            'from_date' => 'from_date',
            'to_date' => 'to_date',
            'status' => 'status',
            'shifts' => 'shifts_count',
        ], 'from_date', 'desc');

        $plans = $query->paginate((int) Setting::get('pagination.duty_plans', 20))->withQueryString();

        return view('duty-plans.index', compact('plans', 'status', 'period', 'sort', 'dir'));
    }

    public function create(): View {
        Gate::authorize('create', DutyPlan::class);

        return view('duty-plans._form_dialog', [
            'dutyPlan' => null,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', DutyPlan::class);

        $data = $this->validated($request);
        $data['created_by'] = Auth::id();

        DutyPlan::create($data);

        return redirect()->route('duty-plans.index')
            ->with('success', __('Dienstplan wurde angelegt.'));
    }

    public function show(DutyPlan $dutyPlan): View {
        Gate::authorize('view', $dutyPlan);

        $dutyPlan->load(['shifts.user:id,name', 'shifts.shiftType']);

        // Schichten nach Datum gruppieren
        $shiftsByDate = $dutyPlan->shifts
            ->sortBy(['date', 'start_time'])
            ->groupBy(fn($s) => $s->date->toDateString());

        // Datumsreihe generieren
        $dates = [];
        $cursor = $dutyPlan->from_date->copy();
        while ($cursor->lte($dutyPlan->to_date)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return view('duty-plans.show', compact('dutyPlan', 'shiftsByDate', 'dates'));
    }

    public function edit(DutyPlan $dutyPlan): View {
        Gate::authorize('update', $dutyPlan);

        return view('duty-plans._form_dialog', [
            'dutyPlan' => $dutyPlan,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, DutyPlan $dutyPlan): RedirectResponse {
        Gate::authorize('update', $dutyPlan);

        $data = $this->validated($request);
        $data['updated_by'] = Auth::id();

        $dutyPlan->update($data);

        return redirect()->route('duty-plans.show', $dutyPlan)
            ->with('success', __('Dienstplan wurde gespeichert.'));
    }

    public function destroy(DutyPlan $dutyPlan): RedirectResponse {
        Gate::authorize('delete', $dutyPlan);

        $dutyPlan->delete();

        return redirect()->route('duty-plans.index')
            ->with('success', __('Dienstplan wurde gelöscht.'));
    }

    public function publish(DutyPlan $dutyPlan): RedirectResponse {
        Gate::authorize('update', $dutyPlan);

        $this->freezeArchiveSnapshot($dutyPlan);
        $dutyPlan->update(['status' => DutyPlanStatus::Published, 'updated_by' => Auth::id()]);

        return back()->with('success', __('Dienstplan wurde veröffentlicht.'));
    }

    /** Zur Genehmigung beantragen (MVP-525): Entwurf → beantragt. */
    public function submit(DutyPlan $dutyPlan): RedirectResponse {
        Gate::authorize('update', $dutyPlan);

        if ($dutyPlan->status !== DutyPlanStatus::Draft) {
            return back()->with('error', __('Nur Entwürfe können zur Genehmigung beantragt werden.'));
        }

        $dutyPlan->update([
            'status' => DutyPlanStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', __('Dienstplan zur Genehmigung beantragt.'));
    }

    /**
     * Genehmigen (MVP-525): beantragt → veröffentlicht; die genehmigte
     * Fassung wird als unveränderlicher Archiv-Snapshot eingefroren.
     */
    public function approve(DutyPlan $dutyPlan): RedirectResponse {
        Gate::authorize('approve', $dutyPlan);

        if ($dutyPlan->status !== DutyPlanStatus::Submitted) {
            return back()->with('error', __('Nur beantragte Dienstpläne können genehmigt werden.'));
        }

        $this->freezeArchiveSnapshot($dutyPlan);
        $dutyPlan->update([
            'status' => DutyPlanStatus::Published,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', __('Dienstplan genehmigt und veröffentlicht.'));
    }

    public function retract(DutyPlan $dutyPlan): RedirectResponse {
        Gate::authorize('update', $dutyPlan);

        $dutyPlan->update(['status' => DutyPlanStatus::Draft, 'updated_by' => Auth::id()]);

        return back()->with('success', __('Dienstplan wurde zurück auf Entwurf gesetzt.'));
    }

    /**
     * Archiv-Snapshot der aktuellen Dienste einfrieren (MVP-525): die
     * „amtliche" Fassung bleibt auch nach späteren Änderungen rekonstruierbar.
     */
    private function freezeArchiveSnapshot(DutyPlan $dutyPlan): void {
        $shifts = $dutyPlan->shifts()
            ->with(['user:id,name', 'shiftType:id,name,abbreviation'])
            ->orderBy('date')
            ->get()
            ->map(fn ($shift): array => [
                'user_id' => (int) $shift->user_id,
                'user' => (string) ($shift->user->name ?? ''),
                'date' => $shift->date->toDateString(),
                'shift_type' => (string) ($shift->shiftType->abbreviation ?? $shift->shiftType->name ?? ''),
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'status' => $shift->status->value,
            ])
            ->values()
            ->all();

        $dutyPlan->forceFill([
            'archive_snapshot' => [
                'frozen_at' => now()->toIso8601String(),
                'frozen_by' => (int) Auth::id(),
                'shifts' => $shifts,
            ],
        ])->save();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'period_type' => ['required', Rule::enum(DutyPlanPeriodType::class)],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'gte:from_date'],
            'min_staff' => ['nullable', 'integer', 'min:0', 'max:255'],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.duty_plan.note_max', 2000)],
        ]);
    }
}
