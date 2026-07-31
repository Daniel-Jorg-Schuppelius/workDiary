<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Attendance\AttendanceStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\{Attendance, User};
use App\Services\Attendance\AttendanceClockService;
use App\Support\{Setting, SortableQuery, Tz};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AttendanceController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(protected AttendanceClockService $clock) {}

    /**
     * Lists attendances for the authenticated user. Zeitraum: explizite
     * from/to-Query-Parameter (Bookmarks), sonst der Header-Zeitraumfilter.
     */
    public function index(Request $request): View {
        Gate::authorize('viewAny', Attendance::class);

        [$from, $to] = $this->resolveRange($request);

        $attendances = Attendance::query()
            ->where('user_id', Auth::id())
            // whereDate statt String-whereBetween: `date` liegt als Datetime vor,
            // sonst fällt der letzte Tag des Zeitraums hinter die Obergrenze.
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString());

        [$sort, $dir] = SortableQuery::apply($attendances, $request, [
            'date' => 'date',
            'started_at' => 'started_at',
            'ended_at' => 'ended_at',
            'duration' => 'duration_minutes',
            'status' => 'status',
            'source' => 'source',
        ], 'started_at', 'desc');

        $attendances = $attendances->paginate(50)->withQueryString();

        /** @var User $user */
        $user = Auth::user();

        return view('attendances.index', [
            'attendances' => $attendances,
            'current' => $this->clock->current($user),
            'from' => $from,
            'to' => $to,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * Tiny widget endpoint returning the current open attendance (for header).
     */
    public function current(): View {
        $user = Auth::user();

        return view('attendances._panel', [
            'current' => $user ? $this->clock->current($user) : null,
        ]);
    }

    public function clockIn(Request $request): RedirectResponse {
        Gate::authorize('create', Attendance::class);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'device' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.device_max', 64)],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
        ]);

        /** @var User $user */
        $user = Auth::user();

        try {
            $this->clock->clockIn($user, $data);
        } catch (RuntimeException $e) {
            return back()->with('error', __('Bereits eingestempelt.'));
        }

        return back()->with('success', __('Eingestempelt.'));
    }

    public function clockOut(Request $request): RedirectResponse {
        Gate::authorize('create', Attendance::class);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'device' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.device_max', 64)],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:' . (int) Setting::get('validation.attendance.break_minutes_max', 600)],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $closed = $this->clock->clockOut($user, $data);
        if (! $closed) {
            return back()->with('error', __('Keine offene Stempelung gefunden.'));
        }

        return back()->with('success', __('Ausgestempelt.'));
    }

    public function break(Request $request): RedirectResponse {
        Gate::authorize('create', Attendance::class);

        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:' . (int) Setting::get('validation.attendance.break_minutes_max', 600)],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $this->clock->addBreak($user, (int) $data['minutes']);

        return back()->with('success', __('Pause hinzugefügt.'));
    }

    public function cancel(): RedirectResponse {
        Gate::authorize('create', Attendance::class);
        /** @var User $user */
        $user = Auth::user();
        $this->clock->cancel($user);

        return back()->with('success', __('Stempelung verworfen.'));
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse {
        Gate::authorize('update', $attendance);

        $data = $request->validate([
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'break_minutes_manual' => ['nullable', 'integer', 'min:0', 'max:' . (int) Setting::get('validation.attendance.break_minutes_max', 600)],
            'note' => ['nullable', 'string', 'max:' . (int) Setting::get('validation.attendance.note_max', 1000)],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
        ]);

        // Manuell eingegebene Zeiten werden in der aktiven Anzeige-Zeitzone
        // interpretiert und zur Speicherung nach UTC umgerechnet.
        $attendance->fill(Arr::except($data, ['started_at', 'ended_at']));
        $attendance->started_at = \Illuminate\Support\Carbon::instance(Tz::parse($data['started_at']));
        $attendance->ended_at = filled($data['ended_at'] ?? null)
            ? \Illuminate\Support\Carbon::instance(Tz::parse($data['ended_at']))
            : null;
        $attendance->updated_by = (int) Auth::id();
        $attendance->save();

        return back()->with('success', __('Stempelung aktualisiert.'));
    }

    public function destroy(Attendance $attendance): RedirectResponse {
        Gate::authorize('delete', $attendance);
        $attendance->delete();

        return back()->with('success', __('Stempelung gelöscht.'));
    }
}
