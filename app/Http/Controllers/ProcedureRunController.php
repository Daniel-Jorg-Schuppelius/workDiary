<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{DiaryEntry, ProcedureRun, ProcedureTemplate, User};
use App\Services\Procedure\{ProcedureApplicabilityResolver, ProcedureExecutionService};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Lese-/Druckansicht durchlaufener Prozedurlaeufe (Feature 026, MVP-025
 * §8.8 „PDF-/Exportdarstellung"). Die Ausfuehrungs-Logik selbst liegt im
 * {@see ProcedureExecutionService}; dieser Controller liefert nur die
 * versionierte Zusammenfassung als druckbare Standalone-HTML-Sicht
 * (Hausmuster: diary/case-file + layouts/print).
 */
class ProcedureRunController extends Controller {
    /**
     * Druckbare Read-Only-Ansicht eines Laufs inkl. aller Schritte,
     * Vier-Augen-Bestaetigungen, Abweichungen und Backup-Nachweise.
     */
    public function print(ProcedureRun $run): View {
        Gate::authorize('view', $run);

        $run->load([
            'templateVersion.template',
            'templateVersion.steps',
            'assignee:id,name',
            'createdBy:id,name',
            'stepRuns.stepDef',
            'stepRuns.executedBy:id,name',
            'stepRuns.secondPerson:id,name',
        ]);

        $stepRuns = $run->stepRuns->sortBy(fn($sr) => $sr->stepDef->sort_order ?? 0)->values();

        $deviations = \App\Models\ProcedureDeviation::query()
            ->whereIn('procedure_step_run_id', $stepRuns->pluck('id'))
            ->with('createdBy:id,name', 'riskAcceptedBy:id,name')
            ->get()
            ->keyBy('procedure_step_run_id');

        $backupProofs = \App\Models\ProcedureBackupProof::query()
            ->whereIn('procedure_step_run_id', $stepRuns->pluck('id'))
            ->with('verifiedBy:id,name')
            ->get()
            ->keyBy('procedure_step_run_id');

        $subject = $run->subject;
        $backUrl = $subject instanceof DiaryEntry ? route('diary.show', $subject) : null;

        return view('procedures.runs.print', [
            'run' => $run,
            'stepRuns' => $stepRuns,
            'deviations' => $deviations,
            'backupProofs' => $backupProofs,
            'subject' => $subject,
            'backUrl' => $backUrl,
            'generatedAt' => now(),
        ]);
    }

    /**
     * Startet einen Prozedurlauf fuer eine anwendbare Vorlage auf einem
     * Auftrag (manuelle/automatische Zuordnung, MVP-025 §8.1). Die
     * Anwendbarkeit wird ueber den {@see ProcedureApplicabilityResolver}
     * gegengeprueft.
     */
    public function start(
        DiaryEntry $diary,
        ProcedureTemplate $template,
        ProcedureExecutionService $execution,
        ProcedureApplicabilityResolver $resolver,
    ): RedirectResponse {
        Gate::authorize('start', ProcedureRun::class);
        abort_unless($template->organization_id === $diary->organization_id, 404);

        /** @var User $actor */
        $actor = Auth::user();

        $applicable = $resolver->suggestFor($diary)->contains(fn($t) => $t->id === $template->id);
        if (! $applicable) {
            return redirect()->route('diary.show', $diary)->with('error', __('procedure.flash.notApplicable'));
        }

        try {
            $execution->start($template, $diary, $actor);
        } catch (\RuntimeException $e) {
            return redirect()->route('diary.show', $diary)->with('error', __('procedure.flash.startFailed'));
        }

        return redirect()->route('diary.show', $diary)->with('success', __('procedure.flash.runStarted'));
    }
}
