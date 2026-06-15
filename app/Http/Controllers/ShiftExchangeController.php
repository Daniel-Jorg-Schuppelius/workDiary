<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Shift\ShiftExchangeStatus;
use App\Enums\User\Permission;
use App\Http\Requests\StoreShiftExchangeRequest;
use App\Models\{ScheduledShift, ShiftExchange, User};
use App\Services\Schedule\{ShiftExchangeException, ShiftExchangeService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Schichttausch mit Freigabe (Feature 007).
 */
class ShiftExchangeController extends Controller {
    public function __construct(private readonly ShiftExchangeService $service) {}

    public function index(): View {
        Gate::authorize('viewAny', ShiftExchange::class);
        /** @var User $auth */
        $auth = Auth::user();

        $canApprove = $auth->hasPermissionTo(Permission::ShiftExchangeApprove->value);

        $base = ShiftExchange::query()
            ->with(['scheduledShift.shiftType', 'offeredShift.shiftType', 'requester', 'targetUser', 'decider'])
            ->latest();

        // Eigene Anträge, an mich gerichtete und – für Leitung – alle offenen.
        $mine = (clone $base)
            ->where(function ($q) use ($auth): void {
                $q->where('requested_by_user_id', $auth->id)
                    ->orWhere('target_user_id', $auth->id);
            })
            ->get();

        $pendingApproval = $canApprove
            ? (clone $base)->open()->get()
            : collect();

        return view('shift-exchanges.index', [
            'mine' => $mine,
            'pendingApproval' => $pendingApproval,
            'canApprove' => $canApprove,
            'statuses' => ShiftExchangeStatus::cases(),
        ]);
    }

    public function store(StoreShiftExchangeRequest $request): RedirectResponse {
        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validated();

        $shift = ScheduledShift::query()->whereKey($data['scheduled_shift_id'])->firstOrFail();
        $target = ! empty($data['target_user_id'])
            ? User::query()->whereKey($data['target_user_id'])->first()
            : null;
        $offered = ! empty($data['offered_shift_id'])
            ? ScheduledShift::query()->whereKey($data['offered_shift_id'])->first()
            : null;

        try {
            $this->service->request($shift, $auth, $target, $offered, $data['reason'] ?? null);
        } catch (ShiftExchangeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('schedule.exchanges.index')
            ->with('success', __('schedule.exchange.requested'));
    }

    public function accept(ShiftExchange $exchange): RedirectResponse {
        Gate::authorize('accept', $exchange);
        /** @var User $auth */
        $auth = Auth::user();

        return $this->run(fn() => $this->service->accept($exchange, $auth), 'schedule.exchange.accepted');
    }

    public function cancel(ShiftExchange $exchange): RedirectResponse {
        Gate::authorize('cancel', $exchange);
        /** @var User $auth */
        $auth = Auth::user();

        return $this->run(fn() => $this->service->cancel($exchange, $auth), 'schedule.exchange.cancelled');
    }

    public function approve(Request $request, ShiftExchange $exchange): RedirectResponse {
        Gate::authorize('decide', $exchange);
        /** @var User $auth */
        $auth = Auth::user();
        $override = $request->boolean('override_compliance');

        return $this->run(
            fn() => $this->service->approve($exchange, $auth, $override),
            'schedule.exchange.approved',
        );
    }

    public function reject(Request $request, ShiftExchange $exchange): RedirectResponse {
        Gate::authorize('decide', $exchange);
        /** @var User $auth */
        $auth = Auth::user();
        $reason = $request->string('reason')->toString() ?: null;

        return $this->run(
            fn() => $this->service->reject($exchange, $auth, $reason),
            'schedule.exchange.rejected',
        );
    }

    /**
     * @param  \Closure(): mixed  $action
     */
    private function run(\Closure $action, string $successKey): RedirectResponse {
        try {
            $action();
        } catch (ShiftExchangeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('schedule.exchanges.index')->with('success', __($successKey));
    }
}
