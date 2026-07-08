<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DpiaController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\{Dpia, DpiaStep, ProcessingActivity};
use App\Services\Privacy\DpiaWorkflowService;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Datenschutz-Folgenabschaetzung (Art. 35) je Verarbeitungstaetigkeit. Eine DSFA
 * pro Taetigkeit; Upsert. Bei outcome != open wird sie als bewertet markiert.
 */
class DpiaController extends Controller {
    public function store(Request $request, ProcessingActivity $activity): RedirectResponse {
        Gate::authorize('create', Dpia::class);
        // Taetigkeit muss zur eigenen Org gehoeren (Scope greift, aber explizit).
        abort_unless((int) $activity->organization_id === (int) ($request->user()?->organization_id), 403);

        $data = $request->validate([
            'necessity' => ['nullable', 'string', 'max:20000'],
            'risks' => ['nullable', 'string', 'max:20000'],
            'mitigations' => ['nullable', 'string', 'max:20000'],
            'residual_risk' => ['nullable', 'in:low,medium,high'],
            'outcome' => ['required', 'in:open,proceed,consult,abort'],
        ]);

        $assessed = $data['outcome'] !== 'open';
        Dpia::query()->updateOrCreate(
            ['organization_id' => $activity->organization_id, 'activity_id' => $activity->id],
            [
                ...$data,
                'assessed_by' => $assessed ? $request->user()?->id : null,
                'assessed_at' => $assessed ? now() : null,
            ],
        );

        // DSFA-Bedarf am VVT markieren.
        $activity->forceFill(['dsfa_required' => true])->save();

        return redirect()->route('dataprotection.activities.show', $activity)
            ->with('status', __('DSFA gespeichert.'));
    }

    /**
     * Geführter Schritt-Workflow (Nachtrag 043a): schließt den benannten
     * Schritt ab; legt DSFA + Schritte bei Bedarf an (Workflow-Einstieg).
     */
    public function completeStep(Request $request, ProcessingActivity $activity, string $stepCode, DpiaWorkflowService $workflow): RedirectResponse {
        Gate::authorize('create', Dpia::class);
        abort_unless((int) $activity->organization_id === (int) ($request->user()?->organization_id), 403);
        abort_unless(in_array($stepCode, DpiaStep::STEPS, true), 404);

        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:20000'],
            'outcome' => ['nullable', 'in:proceed,consult,abort'],
            'residual_risk' => ['nullable', 'in:low,medium,high'],
        ]);

        /** @var Dpia $dpia */
        $dpia = Dpia::query()->firstOrCreate(
            ['organization_id' => $activity->organization_id, 'activity_id' => $activity->id],
        );
        $activity->forceFill(['dsfa_required' => true])->save();
        $workflow->ensureSteps($dpia);

        $step = DpiaStep::query()
            ->where('dpia_id', $dpia->id)
            ->where('step', $stepCode)
            ->firstOrFail();

        /** @var \App\Models\User $actor */
        $actor = $request->user();

        try {
            $workflow->complete($step, $actor, $data['content'] ?? null, $data['outcome'] ?? null, $data['residual_risk'] ?? null);
        } catch (RuntimeException $e) {
            return redirect()->route('dataprotection.activities.show', $activity)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('dataprotection.activities.show', $activity)
            ->with('status', __('DSFA-Schritt abgeschlossen.'));
    }

    /** DSFA-Bericht als PDF (übers pdf-toolkit, Muster IncidentController). */
    public function report(Request $request, ProcessingActivity $activity): Response {
        Gate::authorize('viewAny', Dpia::class);
        abort_unless((int) $activity->organization_id === (int) ($request->user()?->organization_id), 403);

        /** @var Dpia $dpia */
        $dpia = Dpia::query()->where('activity_id', $activity->id)->firstOrFail();
        $dpia->load(['steps.completedBy', 'activity']);

        $html = view('privacy.dpias.report-pdf', [
            'dpia' => $dpia,
            'activity' => $activity,
        ])->render();

        $bytes = PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('PDF-Erzeugung fehlgeschlagen.');

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="dsfa-' . $activity->id . '.pdf"',
        ]);
    }
}
