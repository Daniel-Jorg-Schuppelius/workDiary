<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Holiday;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HolidayController extends Controller {
    public function index(Request $request, HolidayService $holidayService): View {
        Gate::authorize('viewAny', AuditLog::class);

        $year = (int) $request->query('year', (int) Carbon::now()->year);
        if ($year < 1970 || $year > 2100) {
            $year = (int) Carbon::now()->year;
        }

        // Eigene (DB-)Feiertage für die Verwaltung (alle, paginiert nach Datum).
        $customHolidays = Holiday::query()
            ->orderByDesc('is_recurring')
            ->orderBy('date')
            ->get();

        // Indexiere DB-Feiertage nach Y-m-d für das Anzeigejahr,
        // damit wir „Standard“ vs. „eigen“ in der Jahresliste unterscheiden können.
        $customForYear = [];
        foreach ($customHolidays as $h) {
            $date = Carbon::parse((string) $h->date);
            if ($h->is_recurring) {
                $key = sprintf('%04d-%02d-%02d', $year, (int) $date->format('m'), (int) $date->format('d'));
            } else {
                if ((int) $date->format('Y') !== $year) {
                    continue;
                }
                $key = $date->format('Y-m-d');
            }
            $customForYear[$key] = $h;
        }

        $merged = collect($holidayService->forYear($year))
            ->map(function ($name, $dateKey) use ($customForYear) {
                return [
                    'date' => Carbon::parse($dateKey),
                    'name' => $name,
                    'custom' => $customForYear[$dateKey] ?? null,
                ];
            })
            ->sortBy(fn($h) => $h['date']->getTimestamp())
            ->values();

        return view('holidays.index', [
            'year' => $year,
            'merged' => $merged,
            'customHolidays' => $customHolidays,
        ]);
    }

    public function create(): View {
        Gate::authorize('viewAny', AuditLog::class);

        return view('holidays._form_dialog', [
            'holiday' => null,
            'isEdit' => false,
            'isDialog' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('viewAny', AuditLog::class);

        $data = $this->validateInput($request);

        Holiday::query()->create($data + [
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('holidays.index')->with('success', __('Feiertag angelegt.'));
    }

    public function edit(Holiday $holiday): View {
        Gate::authorize('viewAny', AuditLog::class);

        return view('holidays._form_dialog', [
            'holiday' => $holiday,
            'isEdit' => true,
            'isDialog' => true,
        ]);
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse {
        Gate::authorize('viewAny', AuditLog::class);

        $data = $this->validateInput($request, $holiday);

        $holiday->update($data + [
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('holidays.index')->with('success', __('Feiertag aktualisiert.'));
    }

    public function destroy(Holiday $holiday): RedirectResponse {
        Gate::authorize('viewAny', AuditLog::class);

        $holiday->delete();

        return redirect()->route('holidays.index')->with('success', __('Feiertag gelöscht.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateInput(Request $request, ?Holiday $holiday = null): array {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:120'],
            'is_recurring' => ['nullable', 'boolean'],
        ]);

        $isRecurring = (bool) ($data['is_recurring'] ?? false);
        $date = (string) $data['date'];

        $duplicate = Holiday::query()
            ->when($holiday, fn($q) => $q->whereKeyNot($holiday->id))
            ->where('is_recurring', $isRecurring)
            ->where('date', $date)
            ->exists();

        if ($duplicate) {
            abort(422, __('Dieser Feiertag ist bereits vorhanden.'));
        }

        $data['is_recurring'] = $isRecurring;

        return $data;
    }
}
