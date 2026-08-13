<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OvertimeInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\TimeApproval\OvertimeRequestStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{OvertimeRequest, User};
use App\Services\TimeApproval\OvertimeRequestService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Entscheider-Inbox für Überstunden-Anträge (MVP-519) — Muster
 * {@see TimeCorrectionInboxController}.
 */
class OvertimeInboxController extends Controller {
    public function __construct(private readonly OvertimeRequestService $service) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::OvertimeViewTeam->value);

        $statusFilter = (string) $request->input('status', OvertimeRequestStatus::Submitted->value);

        $query = OvertimeRequest::query()
            ->with(['user:id,name', 'requestedBy:id,name', 'decidedBy:id,name'])
            ->orderByDesc('scope_date')
            ->orderByDesc('id');

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        return view('admin.time-approval.overtime.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'filters' => ['status' => $statusFilter],
            'statuses' => OvertimeRequestStatus::cases(),
        ]);
    }

    public function approve(Request $request, OvertimeRequest $overtime): RedirectResponse {
        Gate::authorize('decide', $overtime);

        return $this->decide($request, $overtime, true, __('Antrag genehmigt.'));
    }

    public function reject(Request $request, OvertimeRequest $overtime): RedirectResponse {
        Gate::authorize('decide', $overtime);

        return $this->decide($request, $overtime, false, __('Antrag abgelehnt.'));
    }

    private function decide(Request $request, OvertimeRequest $overtime, bool $approved, string $message): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->decide($overtime, $user, $approved, $data['note'] ?? null);

        // MVP-531: Zwischenstufe — Antrag bleibt offen, Fortschritt melden.
        if ($approved && $overtime->fresh()?->status === OvertimeRequestStatus::Submitted) {
            $progress = app(\App\Services\Approval\ApprovalFlowService::class)
                ->progressFor($overtime, \App\Services\Approval\ApprovalFlowService::TYPE_OVERTIME);

            return back()->with('status', __('Freigabe :done/:required erfasst — die nächste Freigabe muss durch eine andere Person erfolgen.', [
                'done' => $progress->approved,
                'required' => $progress->required,
            ]));
        }

        return back()->with('status', $message);
    }
}
