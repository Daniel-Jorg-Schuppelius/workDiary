<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Schedule;

use App\Enums\Shift\ShiftExchangeStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\StoreShiftExchangeRequest;
use App\Models\Platform\User;
use App\Models\Schedule\{ScheduledShift, ShiftExchange};
use App\Services\Compliance\ComplianceViolation;
use App\Services\Schedule\{ShiftExchangeException, ShiftExchangeService};
use App\Support\Query\DateRange;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Schichttausch mit Freigabe (Feature 007).
 */
class ShiftExchangeController extends Controller {
    private const int OFFER_HORIZON_DAYS = 60;

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
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $pendingApproval = $canApprove
            ? (clone $base)->open()->get()
            : collect();

        return view('shift-exchanges.index', [
            'mine' => $mine,
            'pendingApproval' => $pendingApproval,
            'blocked' => $pendingApproval
                ->filter(fn (ShiftExchange $exchange): bool => $exchange->target_user_id !== null && $this->blockingViolations($exchange) !== [])
                ->pluck('id')->all(),
            'canApprove' => $canApprove,
            'statuses' => ShiftExchangeStatus::cases(),
        ]);
    }

    /** Antrag stellen: eigene kommende Schichten, optional eine Wunschperson (Vollscan 2026-10-21). */
    public function create(): View {
        /** @var User $auth */
        $auth = Auth::user();

        return view('shift-exchanges._request_dialog', [
            'shifts' => ScheduledShift::query()
                ->where('user_id', $auth->id)
                ->where('date', '>=', now()->toDateString())
                ->where('status', '!=', \App\Enums\Shift\ScheduledShiftStatus::Cancelled->value)
                ->with('shiftType')
                ->orderBy('date')
                ->limit(100)
                ->get(),
            'colleagues' => User::inCurrentOrganization()->whereKeyNot($auth->id)->orderBy('name')->get(['id', 'name']),
            // Gegenschichten für den echten Tausch (MVP-1553): kommende Schichten der Kolleginnen und Kollegen.
            'offerableShifts' => ScheduledShift::query()
                ->whereNotNull('user_id')
                ->where('user_id', '!=', $auth->id)
                ->whereBetween('date', DateRange::days(now(), now()->addDays(self::OFFER_HORIZON_DAYS)))
                ->where('status', '!=', \App\Enums\Shift\ScheduledShiftStatus::Cancelled->value)
                ->with(['shiftType', 'user:id,name'])
                ->orderBy('date')
                ->limit(300)
                ->get()
                ->groupBy(static fn (ScheduledShift $shift): string => (string) $shift->user?->name)
                ->sortKeys(),
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

        return redirect()->toList('schedule.exchanges.index')
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

    /** Freigabe mit Regelverstoß: Verstöße zeigen, Übersteuerung nur mit Begründung (MVP-1553). */
    public function approveForm(ShiftExchange $exchange): View {
        Gate::authorize('decide', $exchange);

        return view('shift-exchanges._approve_dialog', [
            'exchange' => $exchange->load(['scheduledShift.shiftType', 'offeredShift.shiftType', 'requester', 'targetUser']),
            'violations' => $this->blockingViolations($exchange),
        ]);
    }

    public function approve(Request $request, ShiftExchange $exchange): RedirectResponse {
        Gate::authorize('decide', $exchange);
        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validate([
            'override_compliance' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->run(
            fn() => $this->service->approve($exchange, $auth, (bool) ($data['override_compliance'] ?? false), $data['override_reason'] ?? null),
            'schedule.exchange.approved',
        );
    }

    /** @return list<string> Meldungen der blockierenden Regelverstöße der geplanten Zuordnung */
    private function blockingViolations(ShiftExchange $exchange): array {
        $messages = [];
        foreach ($this->service->previewCompliance($exchange) as $report) {
            foreach ($report->bySeverity(ComplianceViolation::SEVERITY_ERROR) as $violation) {
                $messages[] = $violation->message;
            }
        }

        return array_values(array_unique($messages));
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

        return redirect()->toList('schedule.exchanges.index')->with('success', __($successKey));
    }
}
