<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthApprovalController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Models\{MonthClosure, User};
use App\Services\TimeApproval\{MonthClosureService, MonthClosureWorkflowException, MonthTotalsSnapshotter};
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Selbstbedienungs-Ansicht für Monatsfreigaben (MVP-016).
 *
 * Mitarbeitende sehen ihre eigenen Monate, können einen Monat einreichen
 * (draft|reopened|rejected → submitted) und einen abgelehnten Monat selbst
 * wieder öffnen (rejected → draft). Alle Admin-Aktionen (approve/reject/
 * reopen/lock) sind im {@see Admin\MonthApprovalInboxController}.
 */
class MonthApprovalController extends Controller {
    public function __construct(
        private readonly MonthClosureService $service,
        private readonly MonthTotalsSnapshotter $snapshotter,
    ) {}

    public function index(Request $request): View {
        /** @var User $user */
        $user = Auth::user();
        Gate::authorize('viewAny', MonthClosure::class);

        $closures = MonthClosure::query()
            ->where('user_id', $user->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(24)
            ->get();

        // Aktuelles Jahr/Monat als Default-Vorschlag, falls keine Zeile existiert.
        $now = CarbonImmutable::now();

        return view('time-approval.month.index', [
            'closures' => $closures,
            'defaultYear' => (int) $now->year,
            'defaultMonth' => (int) $now->month,
        ]);
    }

    public function show(int $year, int $month): View {
        abort_unless($this->service->isValidPeriod($year, $month), 404);

        /** @var User $user */
        $user = Auth::user();
        $closure = $this->service->getOrCreate($user, $year, $month);
        Gate::authorize('view', $closure);

        // Live-Vorschau der Totals, solange noch nicht eingereicht.
        $preview = $closure->status === MonthClosureStatus::Draft
            ? $this->snapshotter->build($user, $year, $month)
            : null;

        return view('time-approval.month.show', [
            'closure' => $closure->load(['events.actor']),
            'preview' => $preview,
        ]);
    }

    public function submit(Request $request, int $year, int $month): RedirectResponse {
        abort_unless($this->service->isValidPeriod($year, $month), 404);

        /** @var User $user */
        $user = Auth::user();
        $closure = $this->service->getOrCreate($user, $year, $month);
        Gate::authorize('submit', $closure);

        try {
            $this->service->submit($closure, $user);
        } catch (MonthClosureWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('month-approval.show', ['year' => $year, 'month' => $month])
            ->with('status', __('Monat :period wurde eingereicht.', ['period' => $closure->periodLabel()]));
    }

    public function reopen(Request $request, int $year, int $month): RedirectResponse {
        abort_unless($this->service->isValidPeriod($year, $month), 404);

        /** @var User $user */
        $user = Auth::user();
        $closure = $this->service->getOrCreate($user, $year, $month);
        Gate::authorize('reopen', $closure);

        try {
            $this->service->reopen($closure, $user);
        } catch (MonthClosureWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('month-approval.show', ['year' => $year, 'month' => $month])
            ->with('status', __('Monat :period wurde zur Bearbeitung geöffnet.', ['period' => $closure->periodLabel()]));
    }
}
