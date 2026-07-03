<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCloseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\TimeApproval\DayClosureStatus;
use App\Models\{DayClosure, DayCorrectionRequest, User};
use App\Services\Attendance\AttendanceClockService;
use App\Services\TimeApproval\{DayCloseService, DayCloseWorkflowException};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Tagesabschluss-Seite (MVP-015, ../WorkDiary-Architecture/tagesabschluss.md).
 *
 * EINE Seite pro Mitarbeitendem (`/tagesabschluss?date=YYYY-MM-DD`):
 * Anwesenheit, Pausen, Buchungen, Warnungen und Bilanz auf einen Blick,
 * dazu die Aktionen day.save / day.close / day.requestCorrection /
 * day.reopen (§2.6). Berechtigte (view.team/.organization) können über
 * `?user=<sqid>` den Tag eines anderen Mitarbeitenden einsehen und dort
 * Korrekturanträge entscheiden (§5 — minimale Inbox-Alternative).
 */
class DayCloseController extends Controller {
    public function __construct(
        private readonly DayCloseService $service,
        private readonly AttendanceClockService $clock,
    ) {}

    public function show(Request $request): View {
        /** @var User $viewer */
        $viewer = Auth::user();
        $day = $this->resolveDay($request);
        $target = $this->resolveTargetUser($request, $viewer);

        // Erst autorisieren, dann anlegen: das `dayClose.opened`-Audit
        // (1×/Tag, §6) entsteht nur, wenn der Eigentümer selbst öffnet.
        $closure = $this->service->find($target, $day);
        if ($closure === null) {
            $probe = new DayClosure([
                'user_id' => $target->id,
                'day' => $day->toDateString(),
                'status' => DayClosureStatus::Open,
            ]);
            $probe->organization_id = (int) $target->organization_id;
            Gate::authorize('view', $probe);

            $closure = $viewer->id === $target->id
                ? $this->service->getOrCreate($target, $day)
                : $probe;
        } else {
            Gate::authorize('view', $closure);
        }

        $context = $this->service->context($target, $day);
        $validator = $this->service->makeValidator();

        $openAttendance = $viewer->id === $target->id ? $this->clock->current($target) : null;

        return view('time-approval.day.show', [
            'closure' => $closure,
            'day' => $day,
            'targetUser' => $target,
            'isOwnDay' => $viewer->id === $target->id,
            'effectiveStatus' => $this->service->effectiveStatus($closure, $context['monthLocked']),
            'monthLocked' => $context['monthLocked'],
            'attendances' => $context['attendances'],
            'entries' => $context['entries'],
            'issues' => $context['issues'],
            'hasBlocking' => $context['hasBlocking'],
            'aggregates' => $context['aggregates'],
            'validator' => $validator,
            'openAttendance' => $openAttendance,
            'isToday' => $day->isSameDay(CarbonImmutable::now()),
            'isFuture' => $day->startOfDay()->greaterThan(CarbonImmutable::now()->endOfDay()),
            'correctionRequests' => $closure->exists ? $closure->correctionRequests()->with(['requestedBy', 'decidedBy'])->get() : collect(),
        ]);
    }

    /** day.save — Audit `dayClose.entrySaved`, kein Status-Wechsel (§6). */
    public function save(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $day = $this->resolveDay($request);
        $closure = $this->service->getOrCreate($user, $day);
        Gate::authorize('save', $closure);

        $this->service->save($closure, $user);

        return $this->redirectToDay($day)
            ->with('status', __('day-close.flash.saved', ['day' => $closure->dayLabel()]));
    }

    /** day.close — open → closed (§2.6/§3). */
    public function close(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $day = $this->resolveDay($request);
        $closure = $this->service->getOrCreate($user, $day);
        Gate::authorize('close', $closure);

        try {
            $this->service->close($closure, $user);
        } catch (DayCloseWorkflowException $e) {
            return $this->redirectToDay($day)->with('error', $e->getMessage());
        }

        return $this->redirectToDay($day)
            ->with('status', __('day-close.flash.closed', ['day' => $closure->dayLabel()]));
    }

    /** day.requestCorrection — closed → correction mit Pflicht-Begründung (§5). */
    public function requestCorrection(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $day = $this->resolveDay($request);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:' . DayCloseService::REASON_MIN_LENGTH, 'max:2000'],
        ]);

        $closure = $this->service->getOrCreate($user, $day);
        Gate::authorize('requestCorrection', $closure);

        try {
            $this->service->requestCorrection($closure, $data['reason'], $user);
        } catch (DayCloseWorkflowException $e) {
            return $this->redirectToDay($day)->with('error', $e->getMessage());
        }

        return $this->redirectToDay($day)
            ->with('status', __('day-close.flash.correction_requested', ['day' => $closure->dayLabel()]));
    }

    /** Korrektur-Freigabe (§5 Schritt 4): Status zurück auf open, Stempel gesperrt. */
    public function approveCorrection(Request $request, DayCorrectionRequest $dayCorrection): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $closure = $dayCorrection->dayClosure;
        abort_unless($closure instanceof DayClosure, 404);
        Gate::authorize('approveCorrection', $closure);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        try {
            $this->service->approveCorrection($dayCorrection, $user, $note);
        } catch (DayCloseWorkflowException $e) {
            return $this->redirectToClosure($closure)->with('error', $e->getMessage());
        }

        return $this->redirectToClosure($closure)
            ->with('status', __('day-close.flash.correction_approved', ['day' => $closure->dayLabel()]));
    }

    /** Korrektur-Ablehnung (§5 Schritt 4): Tag bleibt abgeschlossen. */
    public function rejectCorrection(Request $request, DayCorrectionRequest $dayCorrection): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();
        $closure = $dayCorrection->dayClosure;
        abort_unless($closure instanceof DayClosure, 404);
        Gate::authorize('approveCorrection', $closure);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        try {
            $this->service->rejectCorrection($dayCorrection, $user, $note);
        } catch (DayCloseWorkflowException $e) {
            return $this->redirectToClosure($closure)->with('error', $e->getMessage());
        }

        return $this->redirectToClosure($closure)
            ->with('status', __('day-close.flash.correction_rejected', ['day' => $closure->dayLabel()]));
    }

    /** day.reopen — Admin-Reopen ohne Antrag, Pflicht-Begründung (§2.6/§6). */
    public function reopen(Request $request): RedirectResponse {
        /** @var User $viewer */
        $viewer = Auth::user();
        $day = $this->resolveDay($request);
        $target = $this->resolveTargetUser($request, $viewer);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:' . DayCloseService::REASON_MIN_LENGTH, 'max:2000'],
        ]);

        $closure = $this->service->find($target, $day);
        abort_unless($closure instanceof DayClosure, 404);
        Gate::authorize('reopen', $closure);

        try {
            $this->service->reopen($closure, $data['reason'], $viewer);
        } catch (DayCloseWorkflowException $e) {
            return $this->redirectToClosure($closure)->with('error', $e->getMessage());
        }

        return $this->redirectToClosure($closure)
            ->with('status', __('day-close.flash.reopened', ['day' => $closure->dayLabel()]));
    }

    // ── intern ─────────────────────────────────────────────────────────

    private function resolveDay(Request $request): CarbonImmutable {
        $raw = (string) $request->input('date', '');
        if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $raw);
            if ($parsed instanceof CarbonImmutable) {
                return $parsed->startOfDay();
            }
        }

        return CarbonImmutable::now()->startOfDay();
    }

    /**
     * `?user=<sqid>` erlaubt Berechtigten (view.team/.organization) den
     * Blick auf fremde Tage — die eigentliche Prüfung macht die Policy.
     */
    private function resolveTargetUser(Request $request, User $viewer): User {
        $sqid = (string) $request->input('user', '');
        if ($sqid === '') {
            return $viewer;
        }

        $id = Sqid::decode(User::class, $sqid);
        if ($id === null || $id === $viewer->id) {
            return $viewer;
        }

        /** @var User|null $target */
        $target = User::query()
            ->where('organization_id', $viewer->organization_id)
            ->find($id);
        abort_unless($target instanceof User, 404);

        return $target;
    }

    /**
     * Eigene Tagesaktionen (save/close/requestCorrection) landen wieder auf der
     * zusammengelegten „Heute"-Seite (MVP-015 — Tagesabschluss in „Heute"
     * integriert). Fremdtag-/Admin-Aktionen nutzen redirectToClosure → day-close.show.
     */
    private function redirectToDay(CarbonImmutable $day): RedirectResponse {
        return redirect()->route('today.show', ['date' => $day->toDateString()]);
    }

    private function redirectToClosure(DayClosure $closure): RedirectResponse {
        $params = ['date' => $closure->day->toDateString()];
        if ((int) Auth::id() !== (int) $closure->user_id) {
            $params['user'] = Sqid::encode(User::class, $closure->user_id);
        }

        return redirect()->route('day-close.show', $params);
    }
}
