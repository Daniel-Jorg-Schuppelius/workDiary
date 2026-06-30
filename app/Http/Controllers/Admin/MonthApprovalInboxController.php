<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthApprovalInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\{MonthClosure, User};
use App\Services\TimeApproval\{MonthClosureService, MonthClosureWorkflowException};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Admin-Inbox für Monatsfreigaben (MVP-016 §6).
 *
 * Listet alle Monatsfreigaben der eigenen Organisation, optional gefiltert
 * nach Status/Periode/Mitarbeitenden, und stellt die Übergänge
 * approve/reject/reopen/lock zur Verfügung.
 */
class MonthApprovalInboxController extends Controller {
    private const ALLOWED_SORTS = ['period_year', 'status', 'days_open', 'warnings_count', 'submitted_at'];

    public function __construct(private readonly MonthClosureService $service) {}

    public function index(Request $request): View {
        /** @var User $admin */
        $admin = Auth::user();
        Gate::authorize('viewAny', MonthClosure::class);
        abort_unless(
            $admin->can(P::MonthApprove->value)
                || $admin->can(P::MonthReject->value)
                || $admin->can(P::MonthReopen->value)
                || $admin->can(P::MonthLock->value)
                || $admin->can(P::MonthViewOrganization->value)
                || $admin->can(P::MonthViewTeam->value),
            403,
        );

        $statusFilter = (string) $request->input('status', 'submitted');
        $userFilter = Sqid::decode(User::class, $request->input('user'));
        $yearFilter = $request->filled('year') ? (int) $request->input('year') : null;

        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'period_year';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $query = MonthClosure::query()
            ->where('organization_id', $admin->organization_id)
            ->with('user')
            ->orderBy($sort, $dir)
            ->orderByDesc('period_month')
            ->orderBy('user_id');

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }
        if ($userFilter !== null) {
            $query->where('user_id', $userFilter);
        }
        if ($yearFilter !== null) {
            $query->where('period_year', $yearFilter);
        }

        $closures = $query->paginate(25)->withQueryString();

        $teamUsers = User::query()
            ->where('organization_id', $admin->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.time-approval.month.index', [
            'closures' => $closures,
            'teamUsers' => $teamUsers,
            'filters' => [
                'status' => $statusFilter,
                'user' => $userFilter,
                'year' => $yearFilter,
            ],
            'statuses' => MonthClosureStatus::cases(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function approve(Request $request, MonthClosure $monthClosure): RedirectResponse {
        Gate::authorize('approve', $monthClosure);
        $note = $request->input('note');
        $note = is_string($note) && trim($note) !== '' ? trim($note) : null;

        return $this->dispatch(
            fn() => $this->service->approve($monthClosure, Auth::user(), $note),
            __('Monat freigegeben.'),
        );
    }

    public function reject(Request $request, MonthClosure $monthClosure): RedirectResponse {
        Gate::authorize('reject', $monthClosure);
        $data = $request->validate([
            'note' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        return $this->dispatch(
            fn() => $this->service->reject($monthClosure, $data['note'], Auth::user()),
            __('Monat abgelehnt.'),
        );
    }

    public function reopen(Request $request, MonthClosure $monthClosure): RedirectResponse {
        Gate::authorize('reopen', $monthClosure);
        $data = $request->validate([
            'note' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        return $this->dispatch(
            fn() => $this->service->reopen($monthClosure, Auth::user(), $data['note']),
            __('Monat wieder geöffnet.'),
        );
    }

    public function lock(Request $request, MonthClosure $monthClosure): RedirectResponse {
        Gate::authorize('lock', $monthClosure);

        return $this->dispatch(
            fn() => $this->service->lock($monthClosure, Auth::user()),
            __('Monat gesperrt.'),
        );
    }

    private function dispatch(\Closure $op, string $successMessage): RedirectResponse {
        try {
            $op();
        } catch (MonthClosureWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $successMessage);
    }
}
