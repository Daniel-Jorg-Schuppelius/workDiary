<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HolidayController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(Request $request, HolidayService $holidayService): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        $year = (int) $request->query('year', (int) Carbon::now()->year);
        if ($year < 1970 || $year > 2100) {
            $year = (int) Carbon::now()->year;
        }

        // Eigene (DB-)Feiertage für die Verwaltung (alle, paginiert nach Datum).
        /** @var Collection<int, Holiday> $customHolidays */
        $customHolidays = Holiday::query()
            ->orderByDesc('is_recurring')
            ->orderBy('date')
            ->get();

        // Ordne DB-Feiertage per resolveForYear() den Jahresdaten zu.
        $customForYear = [];
        foreach ($customHolidays as $h) {
            foreach ($h->resolveForYear($year) as $dateKey) {
                $customForYear[$dateKey] = $h;
            }
        }

        $merged = collect($holidayService->forYear($year))
            ->map(function ($name, $dateKey) use ($customForYear) {
                return [
                    'date' => Carbon::parse($dateKey),
                    'name' => $name,
                    'custom' => $customForYear[$dateKey] ?? null,
                ];
            })
            ->sortBy(fn ($h) => $h['date']->getTimestamp())
            ->values();

        return view('holidays.index', [
            'year' => $year,
            'merged' => $merged,
            'customHolidays' => $customHolidays,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        return view('holidays._form_dialog', [
            'holiday' => null,
            'isEdit' => false,
            'isDialog' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $data = $this->validateInput($request);

        Holiday::query()->create($data + [
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('holidays.index')->with('success', __('Feiertag angelegt.'));
    }

    public function edit(Holiday $holiday): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        return view('holidays._form_dialog', [
            'holiday' => $holiday,
            'isEdit' => true,
            'isDialog' => true,
        ]);
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $data = $this->validateInput($request, $holiday);

        $holiday->update($data + [
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('holidays.index')->with('success', __('Feiertag aktualisiert.'));
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $holiday->delete();

        return redirect()->route('holidays.index')->with('success', __('Feiertag gelöscht.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateInput(Request $request, ?Holiday $holiday = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'recurrence_mode' => ['required', 'in:once,yearly,relative'],
            'date' => ['nullable', 'date', 'required_unless:recurrence_mode,relative'],
            'recurrence_weekday' => ['nullable', 'integer', 'between:0,6', 'required_if:recurrence_mode,relative'],
            'recurrence_week' => ['nullable', 'in:-1,1,2,3,4',           'required_if:recurrence_mode,relative'],
            'recurrence_month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $mode = (string) $data['recurrence_mode'];
        $isRelative = $mode === 'relative';
        $isRecurring = $mode !== 'once';

        $result = [
            'name' => $data['name'],
            'recurrence_type' => $isRelative ? 'relative' : 'fixed',
            'is_recurring' => $isRecurring,
            'date' => $isRelative ? null : ($data['date'] ?? null),
            'recurrence_weekday' => $isRelative ? (int) ($data['recurrence_weekday'] ?? 0) : null,
            'recurrence_week' => $isRelative ? (int) ($data['recurrence_week'] ?? 1) : null,
            'recurrence_month' => $isRelative && ! empty($data['recurrence_month'])
                ? (int) $data['recurrence_month'] : null,
        ];

        // Duplikat-Prüfung nur für feste Feiertage
        if (! $isRelative) {
            $duplicate = Holiday::query()
                ->when($holiday, fn ($q) => $q->whereKeyNot($holiday->id))
                ->where('recurrence_type', 'fixed')
                ->where('is_recurring', $isRecurring)
                ->where('date', $result['date'])
                ->exists();

            if ($duplicate) {
                abort(422, __('Dieser Feiertag ist bereits vorhanden.'));
            }
        }

        return $result;
    }
}
