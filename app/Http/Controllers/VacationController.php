<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vacation;
use App\Services\HolidayService;
use App\Support\LookupCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VacationController extends Controller {

    // ── List ───────────────────────────────────────────────────────────────

    public function index(Request $request, HolidayService $holidayService): View {
        /** @var User $auth */
        $auth = Auth::user();
        $isAdmin = $auth->isAdmin();

        $filters = $request->only(['user_id', 'status', 'type', 'from', 'to']);

        $query = Vacation::query()
            ->with(['user', 'decider'])
            ->orderByDesc('start_date');

        // Nicht-Admins sehen nur eigene Einträge
        if (! $isAdmin) {
            $query->where('user_id', $auth->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['from'])) {
            $query->where('end_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('start_date', '<=', $filters['to']);
        }

        $vacations = $query->paginate(25)->withQueryString();

        // KPI counts (ungefiltert per User-Scope)
        $kpiBase = Vacation::query()
            ->when(! $isAdmin, fn($q) => $q->where('user_id', $auth->id));
        $counts = [
            'pending'  => (clone $kpiBase)->where('status', Vacation::STATUS_PENDING)->count(),
            'approved' => (clone $kpiBase)->where('status', Vacation::STATUS_APPROVED)
                              ->where('end_date', '>=', now()->startOfYear())->count(),
            'total'    => (clone $kpiBase)->where('end_date', '>=', now()->startOfYear())->count(),
        ];

        return view('vacations.index', [
            'vacations'     => $vacations,
            'filters'       => $filters,
            'isAdmin'       => $isAdmin,
            'counts'        => $counts,
            'users'         => $isAdmin ? LookupCache::userDropdown() : collect(),
            'holidayService' => $holidayService,
        ]);
    }

    // ── Create / Store ──────────────────────────────────────────────────────

    public function create(Request $request): View {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('create', Vacation::class);

        return view('vacations._form_dialog', [
            'vacation'        => null,
            'isEdit'          => false,
            'isDialog'        => true,
            'canAssignOthers' => $auth->isAdmin(),
            'assignableUsers' => $auth->isAdmin() ? LookupCache::userDropdown() : collect(),
            'prefillStart'    => $request->query('start_date') ?? '',
            'prefillEnd'      => $request->query('end_date')   ?? '',
        ]);
    }

    public function store(Request $request): RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('create', Vacation::class);

        $data = $this->validateVacation($request);

        if (! $auth->isAdmin() || empty($data['user_id'])) {
            $data['user_id'] = $auth->id;
        }
        $data['status'] = Vacation::STATUS_PENDING;

        Vacation::create($data);

        return redirect()->route('vacations.index')->with('success', __('Urlaubsantrag gestellt.'));
    }

    // ── Edit / Update ───────────────────────────────────────────────────────

    public function edit(Vacation $vacation): View {
        /** @var User $auth */
        $auth = Auth::user();
        Gate::authorize('update', $vacation);

        return view('vacations._form_dialog', [
            'vacation'        => $vacation,
            'isEdit'          => true,
            'isDialog'        => true,
            'canAssignOthers' => $auth->isAdmin(),
            'assignableUsers' => $auth->isAdmin() ? LookupCache::userDropdown() : collect(),
            'prefillStart'    => '',
            'prefillEnd'      => '',
        ]);
    }

    public function update(Request $request, Vacation $vacation): RedirectResponse {
        Gate::authorize('update', $vacation);

        $data = $this->validateVacation($request);
        $vacation->update($data);

        return redirect()->route('vacations.index')->with('success', __('Urlaubsantrag aktualisiert.'));
    }

    // ── Delete ──────────────────────────────────────────────────────────────

    public function destroy(Vacation $vacation): RedirectResponse {
        Gate::authorize('delete', $vacation);

        $vacation->delete();

        return redirect()->route('vacations.index')->with('success', __('Urlaubsantrag gelöscht.'));
    }

    // ── Admin actions ────────────────────────────────────────────────────────

    public function rejectForm(Vacation $vacation): View {
        Gate::authorize('decide', $vacation);

        return view('vacations._reject_dialog', [
            'vacation' => $vacation,
        ]);
    }

    public function approve(Request $request, Vacation $vacation): RedirectResponse {
        Gate::authorize('decide', $vacation);

        /** @var User $auth */
        $auth = Auth::user();

        $vacation->update([
            'status'      => Vacation::STATUS_APPROVED,
            'decided_by'  => $auth->id,
            'decided_at'  => now(),
            'reject_reason' => null,
        ]);

        return redirect()->route('vacations.index')->with('success', __('Urlaubsantrag genehmigt.'));
    }

    public function reject(Request $request, Vacation $vacation): RedirectResponse {
        Gate::authorize('decide', $vacation);

        $data = $request->validate([
            'reject_reason' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var User $auth */
        $auth = Auth::user();

        $vacation->update([
            'status'        => Vacation::STATUS_REJECTED,
            'decided_by'    => $auth->id,
            'decided_at'    => now(),
            'reject_reason' => $data['reject_reason'] ?? null,
        ]);

        return redirect()->route('vacations.index')->with('success', __('Urlaubsantrag abgelehnt.'));
    }

    public function cancel(Request $request, Vacation $vacation): RedirectResponse {
        Gate::authorize('cancel', $vacation);

        $vacation->update([
            'status'     => Vacation::STATUS_CANCELLED,
            'decided_by' => null,
            'decided_at' => null,
        ]);

        return redirect()->route('vacations.index')->with('success', __('Urlaubsantrag storniert.'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validateVacation(Request $request): array {
        return $request->validate([
            'user_id'    => ['nullable', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'gte:start_date'],
            'type'       => ['required', 'in:' . implode(',', Vacation::$types)],
            'note'       => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
