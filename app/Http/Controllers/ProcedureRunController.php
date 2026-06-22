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

use App\Enums\Procedure\{ProcedureStepRunStatus, ProcedureStepType};
use App\Exceptions\{ProcedureDeviationValidationException, ProcedureRunIncompleteException, ProcedureSecondPersonException, ProcedureStepBlockedException};
use App\Models\{Attachment, DiaryEntry, ProcedureRun, ProcedureStepRun, ProcedureTemplate, User};
use App\Services\Procedure\{ProcedureApplicabilityResolver, ProcedureExecutionService, SecondPersonGate, WaitStepService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Ausführung und Lese-/Druckansicht von Prozedurläufen (Feature 026).
 *
 * Die {@see show()}-Ansicht ist die mobile, Schritt-für-Schritt ausführbare
 * Sicht aus MVP-063: Sie rendert bedingte Schritte, Warteschritte (MVP-064),
 * Vier-Augen-Freigaben und Medien-Nachweise. Die eigentliche Pflicht-,
 * Reihenfolge- und Sperrlogik liegt im {@see ProcedureExecutionService};
 * dieser Controller validiert Eingaben, lädt Medien hoch und übersetzt
 * Domänen-Exceptions in Flash-Meldungen. {@see print()} liefert zusätzlich
 * die versionierte Zusammenfassung als druckbare Standalone-HTML-Sicht.
 */
class ProcedureRunController extends Controller {
    /**
     * Mobile Ausführungsansicht: alle Schritte in Reihenfolge mit Status,
     * Sperrgrund, dem aktuell ausführbaren Schritt sowie Wartezeit-Restzeit.
     */
    public function show(ProcedureRun $run, ProcedureExecutionService $execution): View {
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

        /** @var User $viewer */
        $viewer = Auth::user();

        $stepRuns = $run->stepRuns
            ->sortBy(fn(ProcedureStepRun $sr) => $sr->stepDef->sort_order ?? 0)
            ->values();

        // Bedingte Schritte (config.depends_on): nicht zutreffende Schritte
        // werden markiert, damit die UI sie als „nicht anwendbar (N/A)"
        // anbietet, statt den Lauf zu blockieren.
        $valuesByStepCode = [];
        foreach ($stepRuns as $sr) {
            $code = (string) ($sr->stepDef->code ?? '');
            if ($code !== '') {
                $valuesByStepCode[$code] = $sr;
            }
        }

        $currentAssigned = false;
        $steps = $stepRuns->map(function (ProcedureStepRun $sr) use ($execution, $viewer, $valuesByStepCode, &$currentAssigned): array {
            $blockReason = $execution->blockReasonFor($sr, $viewer);
            $applicable = $this->isStepApplicable($sr, $valuesByStepCode);
            $isCurrent = false;
            if (! $currentAssigned && ! $sr->status->isFinal() && $blockReason === null && $applicable) {
                $isCurrent = true;
                $currentAssigned = true;
            }

            return [
                'stepRun' => $sr,
                'def' => $sr->stepDef,
                'blockReason' => $blockReason,
                'applicable' => $applicable,
                'isCurrent' => $isCurrent,
                'waitRemaining' => $this->waitRemainingSeconds($sr),
            ];
        });

        $total = $stepRuns->count();
        $done = $stepRuns->filter(fn(ProcedureStepRun $sr) => $sr->status->isFinal())->count();
        $subject = $run->subject;

        return view('procedures.runs.show', [
            'run' => $run,
            'steps' => $steps,
            'subject' => $subject,
            'backUrl' => $subject instanceof DiaryEntry ? route('diary.show', $subject) : route('diary.index'),
            'progressTotal' => $total,
            'progressDone' => $done,
            'canExecute' => Gate::allows('execute', $run),
            'canAbort' => Gate::allows('abort', $run),
            'missingRequired' => $execution->missingRequiredStepRuns($run),
        ]);
    }

    /**
     * Setzt einen Schritt auf einen finalen Status (erledigt / nicht
     * zutreffend / fehlgeschlagen) inkl. optionalem Wert, Notiz und
     * Medien-Nachweis (MVP-063 „Medien").
     */
    public function executeStep(Request $request, ProcedureRun $run, ProcedureStepRun $stepRun, ProcedureExecutionService $execution): RedirectResponse {
        Gate::authorize('execute', $run);
        $this->assertStepBelongsToRun($run, $stepRun);

        $data = $request->validate([
            'status' => ['required', 'in:done,n_a,failed'],
            'value' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'max:20480'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $target = ProcedureStepRunStatus::from($data['status']);

        $payload = [];
        if (array_key_exists('value', $data) && $data['value'] !== null && $data['value'] !== '') {
            $payload['value_json'] = ['value' => (string) $data['value']];
        }
        if (array_key_exists('note', $data)) {
            $payload['note'] = $data['note'];
        }
        if ($request->hasFile('proof')) {
            $payload['proof_attachment_id'] = $this->storeProof($request, $run, $stepRun, $actor);
        }

        try {
            $execution->execute($stepRun, $actor, $target, $payload);
        } catch (ProcedureStepBlockedException $e) {
            return back()->with('error', __('procedure.blocked.' . $e->reason));
        } catch (ProcedureSecondPersonException $e) {
            return back()->with('error', __('procedure.validation.secondPersonMissing'));
        }

        return back()->with('success', __('procedure.flash.stepCompleted'));
    }

    /**
     * Startet die serverseitige Wartezeit eines Warteschritts (MVP-064).
     * Die Dauer kommt aus der Schritt-Konfiguration (`wait_seconds`),
     * fällt aber auf einen Request-Wert zurück.
     */
    public function beginWait(Request $request, ProcedureRun $run, ProcedureStepRun $stepRun, WaitStepService $waits): RedirectResponse {
        Gate::authorize('execute', $run);
        $this->assertStepBelongsToRun($run, $stepRun);

        $configured = (int) ($stepRun->stepDef->config['wait_seconds'] ?? 0);
        $seconds = $configured > 0
            ? $configured
            : (int) $request->validate(['seconds' => ['required', 'integer', 'min:1', 'max:2592000']])['seconds'];

        $waits->beginWait($stepRun, $seconds);

        return back()->with('success', __('procedure.flash.waitStarted'));
    }

    /**
     * Setzt einen Warteschritt fort. Vor Fristablauf nur als auditierte
     * Abweichung mit Begründung (MVP-064), sonst regulär.
     */
    public function continueWait(Request $request, ProcedureRun $run, ProcedureStepRun $stepRun, WaitStepService $waits): RedirectResponse {
        Gate::authorize('execute', $run);
        $this->assertStepBelongsToRun($run, $stepRun);

        /** @var User $actor */
        $actor = Auth::user();
        $elapsed = $waits->canContinue($stepRun);

        if (! $elapsed) {
            $reason = (string) $request->validate([
                'reason' => ['required', 'string', 'min:5', 'max:2000'],
            ])['reason'];
            $waits->continueStep($stepRun, true, $reason, $actor->id);

            return back()->with('success', __('procedure.flash.waitOverridden'));
        }

        $waits->continueStep($stepRun, false, null, $actor->id);

        return back()->with('success', __('procedure.flash.stepCompleted'));
    }

    /**
     * Zeichnet den aktuellen Benutzer als zweite Person gegen (Vier-Augen,
     * MVP-028). Übernahme und Signatur in einem Schritt.
     */
    public function signSecondPerson(ProcedureRun $run, ProcedureStepRun $stepRun, SecondPersonGate $gate): RedirectResponse {
        Gate::authorize('execute', $run);
        $this->assertStepBelongsToRun($run, $stepRun);

        /** @var User $signer */
        $signer = Auth::user();

        try {
            $gate->take($stepRun, $signer);
            $gate->sign($stepRun, $signer);
        } catch (ProcedureSecondPersonException $e) {
            return back()->with('error', __('procedure.validation.secondPersonSelfNotAllowed'));
        }

        return back()->with('success', __('procedure.flash.secondPersonSigned'));
    }

    /** Schließt den Lauf ab (alle Pflichtschritte final, keine kritische Abweichung). */
    public function complete(ProcedureRun $run, ProcedureExecutionService $execution): RedirectResponse {
        Gate::authorize('execute', $run);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $execution->completeRun($run, $actor);
        } catch (ProcedureRunIncompleteException) {
            return back()->with('error', __('procedure.validation.runIncomplete'));
        } catch (ProcedureDeviationValidationException) {
            return back()->with('error', __('procedure.validation.criticalDeviationOpen'));
        }

        return back()->with('success', __('procedure.flash.runCompleted'));
    }

    /** Bricht den Lauf mit Begründung ab. */
    public function abort(Request $request, ProcedureRun $run, ProcedureExecutionService $execution): RedirectResponse {
        Gate::authorize('abort', $run);

        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ])['reason'] ?? null;

        /** @var User $actor */
        $actor = Auth::user();
        $execution->abort($run, $actor, $reason);

        $subject = $run->subject;
        $target = $subject instanceof DiaryEntry ? route('diary.show', $subject) : route('diary.index');

        return redirect($target)->with('success', __('procedure.flash.runAborted'));
    }

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
            $run = $execution->start($template, $diary, $actor);
        } catch (\RuntimeException $e) {
            return redirect()->route('diary.show', $diary)->with('error', __('procedure.flash.startFailed'));
        }

        return redirect()->route('procedure-runs.show', $run)->with('success', __('procedure.flash.runStarted'));
    }

    /**
     * Verknüpft Schritt und Lauf hart, damit man über die Step-Route nicht
     * einen fremden Schritt eines anderen Laufs manipulieren kann.
     */
    private function assertStepBelongsToRun(ProcedureRun $run, ProcedureStepRun $stepRun): void {
        abort_unless((int) $stepRun->procedure_run_id === (int) $run->id, 404);
    }

    /**
     * Wertet eine optionale Bedingung (config.depends_on) eines Schritts
     * gegen den erfassten Wert des Bezugsschritts aus. Ohne Bedingung ist
     * der Schritt immer anwendbar.
     *
     * @param  array<string, ProcedureStepRun>  $valuesByStepCode
     */
    private function isStepApplicable(ProcedureStepRun $stepRun, array $valuesByStepCode): bool {
        $dependsCode = data_get($stepRun->stepDef?->config, 'depends_on.step_code');
        if (! is_string($dependsCode) || $dependsCode === '') {
            return true;
        }

        $reference = $valuesByStepCode[$dependsCode] ?? null;
        if (! $reference instanceof ProcedureStepRun) {
            return true; // Bezugsschritt unbekannt → nicht künstlich blockieren.
        }

        $equals = data_get($stepRun->stepDef?->config, 'depends_on.equals');
        if ($equals === null || $equals === '') {
            // Reine Existenzbedingung: Bezugsschritt muss erledigt sein.
            return $reference->status === ProcedureStepRunStatus::Done;
        }

        return (string) data_get($reference->value_json, 'value') === (string) $equals;
    }

    /** Restliche Wartezeit eines Warteschritts in Sekunden (0 = abgelaufen/keine). */
    private function waitRemainingSeconds(ProcedureStepRun $stepRun): int {
        if ($stepRun->wait_until === null) {
            return 0;
        }

        return (int) max(0, now()->diffInSeconds($stepRun->wait_until, false));
    }

    /**
     * Speichert einen hochgeladenen Medien-Nachweis und gibt die
     * Attachment-ID zurück (für proof_attachment_id). Muster wie
     * {@see AttachmentController::store()}.
     */
    private function storeProof(Request $request, ProcedureRun $run, ProcedureStepRun $stepRun, User $actor): int {
        $file = $request->file('proof');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $folder = 'attachments/procedure-runs/' . now()->format('Y/m');
        $filename = Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');
        $path = $file->storeAs($folder, $filename, 'local');

        $attachment = Attachment::query()->create([
            'organization_id' => $run->organization_id,
            'attachable_type' => $stepRun->getMorphClass(),
            'attachable_id' => $stepRun->id,
            'user_id' => $actor->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => (int) $file->getSize(),
            'meta_type' => 'procedure_proof',
        ]);

        return (int) $attachment->id;
    }
}
