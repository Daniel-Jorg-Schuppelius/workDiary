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
use CommonToolkit\Helper\Data\NumberHelper;
use ERechnungToolkit\Enums\GaebFormat;
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
    use \App\Http\Controllers\Reporting\Concerns\WritesReportCsv;

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
    /**
     * Preisspiegel (MVP-620): mehrere Angebote zu dieser Ausschreibung
     * nebeneinander. Gekennzeichnet wird, nicht gewertet — die Vergabeordnung
     * verlangt bei ungewöhnlich niedrigen Angeboten Aufklärung.
     */
    public function priceComparison(BillOfQuantity $billOfQuantity, \App\Services\Gaeb\PriceComparisonService $comparison): View {
        $this->canView();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        return view('bill-of-quantities.price-comparison', [
            'bill' => $billOfQuantity,
            'comparison' => $comparison->compare($billOfQuantity),
        ]);
    }

    /**
     * Kostengruppen-Auswertung (Feature 109, MVP-642): Summen je Kostengruppe
     * über Positionen und Teilmengen.
     *
     * Der Anteil **ohne Zuordnung** steht immer mit in der Tabelle — ein
     * stillschweigend auf null gesetzter Rest machte die Auswertung wertlos.
     */
    public function costGroups(Request $request, BillOfQuantity $billOfQuantity, \App\Services\Gaeb\CostGroupReportService $report): View|Response {
        $this->canView();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $level = (int) $request->integer('level', 2);
        $level = in_array($level, [1, 2, 3], true) ? $level : 2;

        // Export derselben Zahlen (MVP-644): Wer die Auswertung weiterreicht,
        // soll nicht abtippen müssen.
        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCostGroups($billOfQuantity, $report, $level, $request);
        }

        return view('bill-of-quantities.cost-groups', [
            'bill' => $billOfQuantity,
            'report' => $report->forBill($billOfQuantity, null, $level),
            // Kostenverfolgung (MVP-643) steht auf derselben Seite: Wer die
            // Summe je Gruppe liest, will als Nächstes wissen, was davon
            // ausgeführt ist.
            'lifecycle' => $report->lifecycle($billOfQuantity, null, $level),
            'level' => $level,
        ]);
    }

    /**
     * Zuordnungs-Oberfläche (Feature 109, MVP-639): Kostengruppe je Position
     * setzen, einzeln oder für eine Auswahl.
     *
     * Der Filter „ohne Kostengruppe" ist der eigentliche Arbeitsmodus — was
     * bereits zugeordnet ist, muss niemand ansehen.
     */
    public function catalogAssignment(Request $request, BillOfQuantity $billOfQuantity, \App\Services\Gaeb\CatalogAssignmentService $assignments): View {
        $this->canView();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $catalog = $assignments->costGroupCatalog($billOfQuantity);
        $onlyUnassigned = $request->boolean('unassigned');
        // Drill-down aus der Auswertung (MVP-642): Wer eine Summe anklickt,
        // will die Positionen dahinter sehen - auf derselben Gliederungsebene,
        // auf der er sie gelesen hat.
        $code = trim($request->string('code')->toString());

        $query = BoqItem::query()
            ->where('bill_of_quantity_id', $billOfQuantity->id)
            // Teilmengen samt ihren Zuordnungen mitladen (MVP-639): Eine
            // aufgeteilte Position wird je Teilmenge zugeordnet, nicht als
            // Ganzes — sonst landet der ganze Betrag in einer Gruppe, in die
            // er nur zum Teil gehört.
            ->with(['catalogAssignments', 'section', 'quantitySplits.catalogAssignments'])
            ->orderBy('reference_no');

        if ($catalog !== null && $code !== '') {
            // Die Auswertung fasst auf Ebene 1 und 2 zusammen; wer „310"
            // anklickt, meint auch 311 und 312. Verglichen wird deshalb auf
            // Präfix — ohne die auffüllenden Nullen der Ebene.
            $prefix = rtrim($code, '0');
            $prefix = $prefix === '' ? $code : $prefix;

            $query->whereHas('catalogAssignments', static function ($q) use ($catalog, $prefix): void {
                $q->where('catalog_key', $catalog->catalog_key)
                    ->whereLikeEscaped('code', $prefix, 'prefix');
            });
        }

        if ($catalog !== null && $onlyUnassigned) {
            $query->whereDoesntHave('catalogAssignments', static function ($q) use ($catalog): void {
                $q->where('catalog_key', $catalog->catalog_key);
            });
        }

        return view('bill-of-quantities.catalog-assignment', [
            'bill' => $billOfQuantity,
            'catalog' => $catalog,
            'options' => $catalog === null ? [] : $assignments->options($catalog),
            'items' => $query->paginate(50)->withQueryString(),
            'onlyUnassigned' => $onlyUnassigned,
            'code' => $code,
            'canManage' => Gate::allows(P::ProjectUpdate->value),
        ]);
    }

    /**
     * Zuordnung einer **Teilmenge** setzen oder entfernen (MVP-639).
     *
     * Sie schlägt in der Auswertung die Zuordnung der Position — deshalb hat
     * sie eine eigene Aktion und wird nicht über die Position mitgesetzt.
     */
    public function assignSplitCatalog(Request $request, \App\Models\BoqItemQuantitySplit $split, \App\Services\Gaeb\CatalogAssignmentService $assignments): RedirectResponse {
        $this->canManage();
        abort_unless($split->organization_id === $this->currentOrganization()->id, 404);

        $bill = $split->item?->billOfQuantity;
        $catalog = $bill === null ? null : $assignments->costGroupCatalog($bill);
        if ($catalog === null) {
            return back()->with('error', __('Kein Kostengruppenkatalog im Leistungsverzeichnis.'));
        }

        $data = $request->validate(['code' => ['nullable', 'string', 'max:40']]);

        try {
            $assignments->assign($split, $catalog, $data['code'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Zuordnung gespeichert.'));
    }

    /** Zuordnung einer einzelnen Position setzen oder entfernen. */
    public function assignCatalog(Request $request, BoqItem $boqItem, \App\Services\Gaeb\CatalogAssignmentService $assignments): RedirectResponse {
        $this->canManage();
        abort_unless($boqItem->organization_id === $this->currentOrganization()->id, 404);

        $bill = $boqItem->billOfQuantity;
        $catalog = $bill === null ? null : $assignments->costGroupCatalog($bill);
        if ($catalog === null) {
            return back()->with('error', __('Kein Kostengruppenkatalog im Leistungsverzeichnis.'));
        }

        $data = $request->validate(['code' => ['nullable', 'string', 'max:40']]);

        try {
            $assignments->assign($boqItem, $catalog, $data['code'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Zuordnung gespeichert.'));
    }

    /** Massenzuordnung über die Positionsauswahl. */
    public function assignCatalogBulk(Request $request, BillOfQuantity $billOfQuantity, \App\Services\Gaeb\CatalogAssignmentService $assignments): RedirectResponse {
        $this->canManage();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $catalog = $assignments->costGroupCatalog($billOfQuantity);
        if ($catalog === null) {
            return back()->with('error', __('Kein Kostengruppenkatalog im Leistungsverzeichnis.'));
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['string'],
        ]);

        $ids = [];
        foreach ($data['items'] as $sqid) {
            $id = app(SqidEncoder::class)->decode(BoqItem::class, $sqid);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        // Der Org-Scope liegt am Modell; die LV-Bindung wird zusätzlich
        // geprüft, damit keine fremde Position mitläuft.
        $items = BoqItem::query()
            ->whereKey($ids)
            ->where('bill_of_quantity_id', $billOfQuantity->id)
            ->get();

        try {
            $count = $assignments->assignMany($items, $catalog, $data['code'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':count Positionen zugeordnet.', ['count' => $count]));
    }

    /**
     * Vorschlagsregeln auf ein Verzeichnis anwenden (MVP-640).
     *
     * Der Lauf füllt **nur Lücken**: Was importiert oder von Hand gesetzt ist,
     * bleibt unangetastet.
     */
    public function applyCatalogRules(BillOfQuantity $billOfQuantity, \App\Services\Gaeb\CatalogSuggestionService $suggestions): RedirectResponse {
        $this->canManage();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $result = $suggestions->apply($billOfQuantity);

        return back()->with('success', __(':applied Vorschläge gesetzt, :skipped Positionen unverändert.', [
            'applied' => $result['applied'],
            'skipped' => $result['skipped'],
        ]));
    }

    /**
     * Kostengruppen-Auswertung als CSV/XLSX (MVP-644).
     *
     * Beide Blöcke in einer Datei: die Summen und die Verfolgung. Getrennte
     * Dateien zwängen dazu, sie außerhalb wieder zusammenzufügen.
     */
    private function exportCostGroups(BillOfQuantity $bill, \App\Services\Gaeb\CostGroupReportService $report, int $level, Request $request): Response {
        $summary = $report->forBill($bill, null, $level);
        $lifecycle = $report->lifecycle($bill, null, $level);

        /** @var list<list<string|int|float|null>> $rows */
        $rows = [[
            (string) __('Block'), (string) __('Kostengruppe'), (string) __('Bezeichnung'),
            (string) __('Summe'), (string) __('Anteil'), (string) __('LV-Ansatz'),
            (string) __('Nachträge'), (string) __('Aufgemessen'), (string) __('Rest'),
        ]];

        foreach ($summary['rows'] as $row) {
            $rows[] = [
                (string) __('Summen'), $row['code'], $row['label'],
                NumberHelper::toGermanFormat($row['amount'], 2),
                NumberHelper::toGermanFormat($row['share'], 1), '', '', '', '',
            ];
        }
        $rows[] = [
            (string) __('Summen'), '', (string) __('Ohne Zuordnung'),
            NumberHelper::toGermanFormat($summary['unassigned'], 2), '', '', '', '', '',
        ];

        foreach ($lifecycle['rows'] as $row) {
            $rows[] = [
                (string) __('Kostenverfolgung'), $row['code'], $row['label'], '', '',
                NumberHelper::toGermanFormat($row['boq'], 2),
                NumberHelper::toGermanFormat($row['addenda'], 2),
                NumberHelper::toGermanFormat($row['executed'], 2),
                NumberHelper::toGermanFormat($row['remaining'], 2),
            ];
        }

        return $this->csvWithMetadata(
            $rows,
            'kostengruppen_' . $bill->sqid . '.csv',
            'boq.cost_groups',
            ['bill' => $bill->sqid, 'level' => $level],
            $request,
        );
    }

    /**
     * Kostenermittlung aus dem LV-Stand erzeugen und als X51 ausgeben
     * (Feature 109, MVP-646).
     *
     * Erzeugt werden nur **Kostenanschlag** und **Kostenfeststellung** — für
     * Schätzung und Berechnung fehlt WorkDiary die Datengrundlage (D5).
     */
    public function costEstimateExport(Request $request, BillOfQuantity $billOfQuantity, \App\Services\Gaeb\CostEstimateService $estimates): Response {
        $this->canManage();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $stage = (string) $request->query('stage', \App\Models\Costing\CostEstimate::STAGE_QUOTE);
        abort_unless(in_array($stage, [
            \App\Models\Costing\CostEstimate::STAGE_QUOTE,
            \App\Models\Costing\CostEstimate::STAGE_FINAL,
        ], true), 422);

        $estimate = $estimates->deriveFromBill($billOfQuantity, $stage, $this->actorOrFail());
        $xml = $estimates->export($estimate);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="Kostenermittlung-' . $billOfQuantity->sqid . '.x51"',
        ]);
    }

    /** Fremde Kostenermittlung als Budget übernehmen (MVP-646). */
    public function costEstimateImport(Request $request, \App\Services\Gaeb\CostEstimateService $estimates): RedirectResponse {
        $this->canImport();

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'project' => ['nullable', 'string'],
        ]);

        $projectId = null;
        $raw = $request->input('project');
        if (is_string($raw) && $raw !== '') {
            $projectId = app(SqidEncoder::class)->decode(Project::class, $raw);
        }
        $project = $projectId === null ? null : Project::query()->find($projectId);

        try {
            $estimate = $estimates->import(
                (string) file_get_contents($request->file('file')->getRealPath()),
                $this->currentOrganization()->id,
                $this->actorOrFail(),
                $project,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Kostenermittlung „:name" übernommen (:count Elemente).', [
            'name' => $estimate->name,
            'count' => $estimate->items()->count(),
        ]));
    }

    private function actorOrFail(): \App\Models\User {
        $user = Auth::user();
        abort_unless($user instanceof \App\Models\User, 403);

        return $user;
    }

    /**
     * Ausgabenwechsel eines Kostengruppenkatalogs (MVP-641).
     *
     * Ohne `confirm` bleibt es bei der **Vorschau**: Ein Wechsel der Norm ist
     * eine fachliche Entscheidung, und die Lücken der Zuordnungstabelle sind
     * das Eigentliche — sie zeigen, wo jemand entscheiden muss.
     */
    public function catalogEdition(Request $request, BillOfQuantity $billOfQuantity, \App\Services\Gaeb\CatalogEditionMigrationService $migration, \App\Services\Gaeb\CatalogAssignmentService $assignments): View|RedirectResponse {
        $this->canView();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $catalog = $assignments->costGroupCatalog($billOfQuantity);
        $from = $catalog === null ? null : $assignments->registryFor($catalog);

        $targets = \App\Models\Catalog\CatalogRegistry::query()
            ->visibleFor($this->currentOrganization()->id)
            ->where('kind', \App\Models\Catalog\CatalogRegistry::KIND_COST_GROUP)
            ->where('active', true)
            ->when($from !== null, fn($q) => $q->whereKeyNot($from?->id))
            ->orderBy('name')
            ->get();

        $to = null;
        $selected = $request->query('to');
        if (is_string($selected) && $selected !== '') {
            $id = app(SqidEncoder::class)->decode(\App\Models\Catalog\CatalogRegistry::class, $selected);
            $to = $id === null ? null : $targets->firstWhere('id', $id);
        }

        $preview = ($catalog !== null && $from !== null && $to !== null)
            ? $migration->preview($billOfQuantity, $catalog, $from, $to)
            : null;

        if ($request->boolean('confirm') && $catalog !== null && $from !== null && $to !== null) {
            $this->canManage();
            $result = $migration->apply($billOfQuantity, $catalog, $from, $to);

            return redirect()
                ->route('bill-of-quantities.cost-groups', $billOfQuantity)
                ->with('success', __(':changed Zuordnungen umgestellt, :unmapped ohne Entsprechung unverändert.', [
                    'changed' => $result['changed'],
                    'unmapped' => $result['unmapped'],
                ]));
        }

        return view('bill-of-quantities.catalog-edition', [
            'bill' => $billOfQuantity,
            'catalog' => $catalog,
            'from' => $from,
            'to' => $to,
            'targets' => $targets,
            'preview' => $preview,
            'canManage' => Gate::allows(P::ProjectUpdate->value),
        ]);
    }

    public function export(Request $request, BillOfQuantity $billOfQuantity): Response {
        $this->canView();
        abort_unless($billOfQuantity->organization_id === $this->currentOrganization()->id, 404);

        $requested = $request->query('phase');
        $phase = (is_string($requested) ? GaebPhase::tryFrom($requested) : null) ?? GaebPhase::Award;

        $wanted = $request->query('format');
        $format = is_string($wanted) ? GaebFormat::tryFrom($wanted) : null;

        $result = $this->exportService->export($billOfQuantity, $phase, Auth::user()?->id, $format);

        // Endung und Inhaltstyp folgen dem geschriebenen Format, nicht der
        // Annahme, es sei XML: Ein GAEB-90-Export als „.x86" wäre schlicht
        // falsch benannt.
        $filename = sprintf(
            'LV-%s.%s',
            $billOfQuantity->sqid,
            $result['format'] === GaebFormat::Da11
                ? 'd11'
                : $result['format']->extensionPrefix() . $phase->value
        );

        return response($result['xml'], 200, [
            'Content-Type' => $result['format'] === GaebFormat::DaXml ? 'application/xml' : 'text/plain; charset=utf-8',
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
