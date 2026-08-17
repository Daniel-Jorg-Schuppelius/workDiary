<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillOfQuantityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Gaeb\{BoqItemStatus, BoqItemType, BoqProgressSource, GaebPhase};
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\{Article, BillOfQuantity, BoqItem, Material, Project};
use App\Services\Gaeb\{BoqCostingService, BoqExportService, BoqImportConflictException, BoqProgressService, BoqWorkflowException, BoqWorkflowService, GaebImportService};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * GAEB-Leistungsverzeichnisse (Feature 049, MVP-081..085): LV-Übersicht,
 * Detailansicht, GAEB-Import mit Preflight, Aufmaß/Fortschritt, Verknüpfung,
 * LV-Workflow (Status/Nachtrag/Restleistung) und GAEB-Export. Modul-Gating über
 * `bill-of-quantities.*` → module.bau; lesen mit project.viewAny, ändern mit
 * project.update, importieren mit project.import.
 */
class BillOfQuantityController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly GaebImportService $importer,
        private readonly BoqProgressService $progressService,
        private readonly BoqWorkflowService $workflow,
        private readonly BoqCostingService $costing,
        private readonly BoqExportService $exportService,
    ) {}

    public function index(): View {
        $this->canView();

        return view('bill-of-quantities.index', [
            'bills' => BillOfQuantity::query()
                ->with('project:id,name')
                ->withCount('items')
                ->orderByDesc('id')
                ->paginate(25),
        ]);
    }

    public function show(BillOfQuantity $billOfQuantity): View {
        $this->canView();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $billOfQuantity->load([
            'project:id,name',
            'items' => fn ($q) => $q->orderBy('position'),
            'items.section:id,reference_no,label',
            'items.progress',
            'items.mappings.mappable',
        ]);

        return view('bill-of-quantities.show', [
            'bill' => $billOfQuantity,
            'imports' => $billOfQuantity->imports()->orderByDesc('id')->limit(20)->get(),
            'costing' => $this->costing->summarize($billOfQuantity),
            'remaining' => $this->workflow->remainingItems($billOfQuantity),
            'canManage' => Auth::user()?->can(P::ProjectUpdate->value) ?? false,
            'billStatuses' => BoqItemStatus::cases(),
        ]);
    }

    /** MVP-083: Aufmaß-/Fortschrittsmeldung zu einer Position erfassen. */
    public function recordProgress(Request $request, BoqItem $boqItem): RedirectResponse {
        $this->canManage();
        abort_unless($boqItem->organization_id === $this->currentOrganization()->id, 404);

        $validated = $request->validate([
            'quantity' => ['required', 'numeric'],
            'source' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->progressService->record($boqItem, (string) $validated['quantity'], [
            'source' => BoqProgressSource::tryFrom((string) ($validated['source'] ?? 'manual')) ?? BoqProgressSource::Manual,
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::user()?->id,
        ]);

        return back()->with('success', __('gaeb.progress.flash.recorded'));
    }

    /** MVP-083: LV-Position mit Artikel/Material verknüpfen. */
    public function addMapping(Request $request, BoqItem $boqItem): RedirectResponse {
        $this->canManage();
        abort_unless($boqItem->organization_id === $this->currentOrganization()->id, 404);

        $validated = $request->validate([
            'target_type' => ['required', 'in:article,material'],
            'target' => ['required', 'string'],
            'factor' => ['nullable', 'numeric'],
        ]);

        $class = $validated['target_type'] === 'article' ? Article::class : Material::class;
        $targetId = app(SqidEncoder::class)->decode($class, (string) $validated['target']);
        abort_unless($targetId !== null && $class::query()->whereKey($targetId)->exists(), 422);

        $boqItem->mappings()->updateOrCreate(
            ['mappable_type' => $class, 'mappable_id' => $targetId],
            ['organization_id' => $boqItem->organization_id, 'factor' => (string) ($validated['factor'] ?? '1'), 'created_by' => Auth::user()?->id],
        );

        return back()->with('success', __('gaeb.mapping.flash.linked'));
    }

    /** MVP-084: Status einer einzelnen Position ändern. */
    public function transitionItem(Request $request, BoqItem $boqItem): RedirectResponse {
        $this->canManage();
        abort_unless($boqItem->organization_id === $this->currentOrganization()->id, 404);

        $validated = $request->validate(['status' => ['required', 'string']]);
        $to = BoqItemStatus::tryFrom((string) $validated['status']);
        abort_unless($to !== null, 422);

        try {
            $this->workflow->transitionItem($boqItem, $to);
        } catch (BoqWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('gaeb.workflow.flash.item_updated'));
    }

    /** MVP-084: Status des gesamten LV ändern. */
    public function transition(Request $request, BillOfQuantity $billOfQuantity): RedirectResponse {
        $this->canManage();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $validated = $request->validate(['status' => ['required', 'string']]);
        $to = BoqItemStatus::tryFrom((string) $validated['status']);
        abort_unless($to !== null, 422);

        try {
            $this->workflow->transitionBill($billOfQuantity, $to);
        } catch (BoqWorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('gaeb.workflow.flash.bill_updated'));
    }

    /** MVP-084: Nachtrag als eigene LV-Position anlegen. */
    public function addAddendum(Request $request, BillOfQuantity $billOfQuantity): RedirectResponse {
        $this->canManage();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $validated = $request->validate([
            'reference_no' => ['required', 'string', 'max:60'],
            'short_text' => ['nullable', 'string', 'max:500'],
            'quantity' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:16'],
            'unit_price' => ['nullable', 'numeric'],
        ]);

        abort_if(
            $billOfQuantity->items()->where('reference_no', $validated['reference_no'])->exists(),
            422,
        );

        $this->workflow->createAddendum($billOfQuantity, [
            'reference_no' => (string) $validated['reference_no'],
            'short_text' => $validated['short_text'] ?? null,
            'quantity' => isset($validated['quantity']) ? (string) $validated['quantity'] : null,
            'unit' => $validated['unit'] ?? null,
            'unit_price' => isset($validated['unit_price']) ? (string) $validated['unit_price'] : null,
            'type' => BoqItemType::Standard,
            'created_by' => Auth::user()?->id,
        ]);

        return back()->with('success', __('gaeb.workflow.flash.addendum_added'));
    }

    /** MVP-085: GAEB-DA-XML-Export eines LV-Stands herunterladen. */
    public function export(Request $request, BillOfQuantity $billOfQuantity): Response {
        $this->canView();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $requested = $request->query('phase');
        $phase = (is_string($requested) ? GaebPhase::tryFrom($requested) : null) ?? GaebPhase::Award;

        $result = $this->exportService->export($billOfQuantity, $phase, Auth::user()?->id);

        $filename = sprintf('LV-%s.x%s', $billOfQuantity->sqid, $phase->value);

        return response($result['xml'], 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function importForm(): View {
        $this->canImport();

        return view('bill-of-quantities.import', [
            'isDialog' => true,
            'projects' => Project::query()->orderBy('name')->limit(500)->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
        ]);
    }

    public function import(Request $request): RedirectResponse {
        $this->canImport();

        $validated = $request->validate([
            // Alle drei GAEB-Familien: DA XML ist XML, GAEB 90 und 2000 sind
            // Textdateien — die Familie erkennt der Reader am Inhalt.
            'file' => ['required', 'file', 'max:20480', 'mimetypes:text/xml,application/xml,text/plain'],
            'project' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $projectId = null;
        if (!empty($validated['project'])) {
            $projectId = app(SqidEncoder::class)->decode(Project::class, (string) $validated['project']);
            if ($projectId !== null) {
                abort_unless(
                    Project::query()->whereKey($projectId)->exists(),
                    422,
                );
            }
        }

        $content = (string) file_get_contents($request->file('file')->getRealPath());
        $filename = (string) $request->file('file')->getClientOriginalName();

        try {
            $import = $this->importer->import($content, $filename, $this->currentOrganization()->id, [
                'project_id' => $projectId,
                'name' => $validated['name'] ?? null,
                'created_by' => Auth::user()?->id,
            ]);
        } catch (BoqImportConflictException $e) {
            return back()->with('error', __('gaeb.flash.conflict', ['refs' => implode(', ', $e->conflictingRefs)]));
        }

        if ($import->status->value === 'preflight_failed') {
            $errors = $import->preflight['errors'] ?? [];

            return back()
                ->with('error', __('gaeb.flash.preflight_failed', ['count' => count($errors)]))
                ->with('gaebErrors', $errors);
        }

        return redirect()
            ->route('bill-of-quantities.show', $import->billOfQuantity)
            ->with('success', __('gaeb.flash.imported', ['items' => $import->item_count]));
    }

    private function canView(): void {
        Gate::authorize(P::ProjectViewAny->value);
    }

    private function canImport(): void {
        Gate::authorize(P::ProjectImport->value);
    }

    private function canManage(): void {
        Gate::authorize(P::ProjectUpdate->value);
    }
}
