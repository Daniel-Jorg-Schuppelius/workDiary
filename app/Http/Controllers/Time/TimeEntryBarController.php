<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryBarController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Time;

use App\Enums\Diary\Mode;
use App\Http\Controllers\Concerns\BuildsTimeEntryOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Time\QuickTimeEntryRequest;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use App\Models\Time\TimeEntry;
use App\Services\Timekeeping\ManualTimeBooking;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, RedirectResponse};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Support\Str;

/**
 * Eingabeleiste auf „Heute" (Toggl-artig): manuelle Buchung (Dauer oder
 * Von/Bis) direkt mit Projektwahl, plus Options-Endpunkt für die vom
 * gewählten Projekt abhängigen Aufgaben-/Auftrags-Selects.
 */
class TimeEntryBarController extends Controller {
    use BuildsTimeEntryOptions;

    public function store(QuickTimeEntryRequest $request, ManualTimeBooking $booking): RedirectResponse {
        Gate::authorize('create', TimeEntry::class);

        /** @var User $user */
        $user = Auth::user();
        $data = $request->validated();
        $isRange = filled($data['started_at'] ?? null) && filled($data['ended_at'] ?? null);

        $timeEntry = $booking->book($user, $data);

        // Der gewählte lokale Kalendertag bestimmt die Rücksprung-Ansicht —
        // nicht der UTC-Tag von started_at (kann beim Umrechnen abweichen).
        $dateString = CarbonImmutable::parse((string) $data['date'])->toDateString();

        $redirect = redirect()->route('today.show', ['date' => $dateString])
            ->with('status', __('Zeit auf „:project" gebucht.', ['project' => (string) $timeEntry->project?->name]));

        if ($isRange) {
            // Ketten-Erfassung: die nächste Buchung setzt dort an, wo diese
            // endet — Datum UND Startzeit werden in der Leiste vorbelegt
            // (läuft der Eintrag über Mitternacht, springt das Datum auf den
            // Folgetag). Flash-Werte gelten nur für den nächsten Request.
            $endLocal = CarbonImmutable::parse((string) $data['ended_at'], 'UTC')->setTimezone(Tz::current());
            $redirect->with('entryBar.nextDate', $endLocal->toDateString())
                ->with('entryBar.nextStart', $endLocal->format('H:i'));
        }

        return $redirect;
    }

    /**
     * Projektabhängige Auswahloptionen für die Leiste (Fetch bei Projektwechsel).
     */
    public function options(Project $project): JsonResponse {
        Gate::authorize('create', TimeEntry::class);

        return response()->json([
            'tasks' => $this->taskOptions($project)
                ->map(fn(Task $t): array => ['id' => $t->sqid, 'title' => (string) $t->title])
                ->values(),
            'diaryEntries' => $this->diaryOptions($project)
                ->map(fn(DiaryEntry $d): array => ['id' => $d->sqid, 'label' => $this->diaryLabel($d)])
                ->values(),
        ]);
    }

    /** Anzeige-Label eines Auftrags (wie im Erfassungs-Dialog). */
    private function diaryLabel(DiaryEntry $entry): string {
        $label = $entry->title ?: Str::limit((string) $entry->content, 60);
        if ($entry->mode !== Mode::Fixed) {
            $label .= ' · ' . $entry->modeLabel();
        }

        return $label;
    }
}
