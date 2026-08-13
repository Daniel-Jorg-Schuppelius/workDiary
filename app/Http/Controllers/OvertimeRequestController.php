<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OvertimeRequestController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\TimeApproval\OvertimeRequestStatus;
use App\Models\{OvertimeRequest, User};
use App\Services\TimeApproval\OvertimeRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Mitarbeiter-Ansicht für Überstunden-Anträge (MVP-519).
 *
 * Listet eigene Anträge, erlaubt Einreichen und Zurückziehen.
 * Entscheidungen liegen im {@see Admin\OvertimeInboxController}.
 */
class OvertimeRequestController extends Controller {
    public function __construct(private readonly OvertimeRequestService $service) {}

    public function index(Request $request): View {
        /** @var User $user */
        $user = Auth::user();
        Gate::authorize('viewAny', OvertimeRequest::class);

        $statusFilter = (string) $request->input('status', '');

        $query = OvertimeRequest::query()
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)->orWhere('requested_by_user_id', $user->id);
            })
            ->with(['user:id,name', 'decidedBy:id,name'])
            ->orderByDesc('scope_date')
            ->orderByDesc('id');

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        return view('time-approval.overtime.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'filters' => ['status' => $statusFilter],
            'statuses' => OvertimeRequestStatus::cases(),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', OvertimeRequest::class);

        $scopeDate = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'))
            : CarbonImmutable::now()->subDay();

        return view('time-approval.overtime._form_dialog', [
            'isDialog' => true,
            'scopeDate' => $scopeDate,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', OvertimeRequest::class);
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'scope_date' => ['required', 'date'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'reason' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        $this->service->submit(
            $user,
            $user,
            CarbonImmutable::parse((string) $data['scope_date']),
            (int) $data['minutes'],
            (string) $data['reason'],
        );

        return redirect()
            ->route('overtime.index')
            ->with('status', __('Überstunden-Antrag eingereicht.'));
    }

    public function withdraw(OvertimeRequest $overtime): RedirectResponse {
        Gate::authorize('withdraw', $overtime);
        /** @var User $user */
        $user = Auth::user();

        $this->service->withdraw($overtime, $user);

        return back()->with('status', __('Antrag zurückgezogen.'));
    }
}
