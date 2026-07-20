<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\{TimeCorrectionRequest, User};
use App\Services\TimeApproval\{TimeCorrectionService, TimeCorrectionWorkflowException};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Admin-Inbox für Zeit-Korrekturanträge (MVP-017 §8).
 *
 * Listet alle Anträge der eigenen Organisation, gefiltert nach Status,
 * und stellt approve/reject/apply zur Verfügung.
 */
class TimeCorrectionInboxController extends Controller {
    private const ALLOWED_SORTS = ['scope_date', 'status'];

    public function __construct(private readonly TimeCorrectionService $service) {}

    public function index(Request $request): View {
        /** @var User $admin */
        $admin = Auth::user();
        Gate::authorize('viewAny', TimeCorrectionRequest::class);
        abort_unless(
            $admin->can(P::CorrectionApprove->value)
                || $admin->can(P::CorrectionReject->value)
                || $admin->can(P::CorrectionApplySystem->value)
                || $admin->can(P::CorrectionViewTeam->value)
                || $admin->can(P::CorrectionViewOrganization->value),
            403,
        );

        $statusFilter = (string) $request->input('status', 'submitted');

        // Whitelist-Auflösung zentral (C21; Vollaudit 2026-07, N26) — bei
        // ungültigem Key fallen Key UND Richtung auf die Defaults zurück.
        [$sort, $dir] = \App\Support\SortableQuery::resolve($request, self::ALLOWED_SORTS, 'scope_date');

        $query = TimeCorrectionRequest::query()
            ->where('organization_id', $admin->organization_id)
            ->with(['user', 'requestedBy', 'items'])
            ->orderBy($sort, $dir)
            ->orderByDesc('id');

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $requests = $query->paginate(25)->withQueryString();

        return view('admin.time-approval.correction.index', [
            'requests' => $requests,
            'filters' => ['status' => $statusFilter],
            'statuses' => TimeCorrectionStatus::cases(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function show(TimeCorrectionRequest $correction): View {
        Gate::authorize('view', $correction);

        return view('admin.time-approval.correction.show', [
            'request' => $correction->load(['items', 'user', 'requestedBy', 'decidedBy']),
        ]);
    }

    public function approve(Request $request, TimeCorrectionRequest $correction): RedirectResponse {
        Gate::authorize('approve', $correction);
        /** @var User $admin */
        $admin = Auth::user();
        $note = $request->input('note');
        $note = is_string($note) && trim($note) !== '' ? trim($note) : null;

        return $this->dispatch(
            fn() => $this->service->approve($correction, $admin, $note),
            __('Antrag genehmigt.'),
        );
    }

    public function reject(Request $request, TimeCorrectionRequest $correction): RedirectResponse {
        Gate::authorize('reject', $correction);
        /** @var User $admin */
        $admin = Auth::user();
        $data = $request->validate([
            'note' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        return $this->dispatch(
            fn() => $this->service->reject($correction, $admin, $data['note']),
            __('Antrag abgelehnt.'),
        );
    }

    public function apply(TimeCorrectionRequest $correction): RedirectResponse {
        Gate::authorize('apply', $correction);

        return $this->dispatch(
            fn() => $this->service->apply($correction),
            __('Antrag angewendet.'),
        );
    }

    private function dispatch(\Closure $op, string $successMessage): RedirectResponse {
        try {
            $op();
        } catch (TimeCorrectionWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $successMessage);
    }
}
