<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StopwatchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\StartStopwatchRequest;
use App\Models\{Project, Timesheet};
use App\Services\Timesheet\{Stopwatch, TimesheetResolver};
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class StopwatchController extends Controller {
    public function __construct(
        protected Stopwatch $stopwatch,
        protected TimesheetResolver $resolver,
    ) {}

    public function current(): View {
        return view('stopwatch._panel', [
            'current' => $this->stopwatch->current($this->authUser()),
        ]);
    }

    public function start(StartStopwatchRequest $request): RedirectResponse {
        $data = $request->validated();

        $project = Project::findOrFail((int) $data['project_id']);

        // Offenen Heute-Stundenzettel verwenden oder anlegen — derselbe Weg wie
        // die Anlage über die Sidebar, sonst entstehen zwei Zettel für denselben
        // Tag und die gestoppten Zeiten landen im falschen.
        $timesheet = isset($data['timesheet_id'])
            ? Timesheet::findOrFail((int) $data['timesheet_id'])
            : $this->resolver->openOrCreate($project, (int) Auth::id(), CarbonImmutable::today())[0];

        Gate::authorize('update', $timesheet);

        try {
            $this->stopwatch->start($this->authUser(), $timesheet, $data['task_id'] ?? null, $data['description'] ?? null, $data['diary_entry_id'] ?? null);
        } catch (\RuntimeException $e) {
            // Doppel-Submit-Guard: nur den Läuft-schon-Fall in eine Flash-Meldung
            // übersetzen; andere Zustände (z. B. signierter Stundenzettel) bleiben hart.
            if ($e->getMessage() !== 'A running entry already exists.') {
                throw $e;
            }

            return back()->with('error', __('Es läuft bereits eine Zeiterfassung.'));
        }

        return back()->with('success', __('Stoppuhr gestartet.'));
    }

    public function stop(): RedirectResponse {
        $this->stopwatch->stop($this->authUser());

        return back()->with('success', __('Stoppuhr gestoppt.'));
    }
}
