<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTemplateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Http\Controllers\Controller;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, IncomingInvoiceTemplate};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Invoicing\Recognition\Contracts\WordBoxSource;
use App\Services\Invoicing\Recognition\{PageImages, TemplateFingerprint, TemplateMatcher, TemplateMerger, TemplateRecognizer, ZoneExtractor, ZoneFields};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;
use InvalidArgumentException;
use PDFToolkit\Entities\WordBox;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Belegvorlagen des Rechnungseingangs. Zonen-Editor am Beleg (Phase 151,
 * MVP-1220): Seitenbild, Wortboxen als Overlay, Zonen je Feld ziehen, Wert
 * prüfen, als Vorlage des Lieferanten speichern und auf die offenen Eingänge
 * derselben Form anwenden. Seit Phase 155: Anker aus dem gezeichneten Rechteck,
 * Seitenbereich, Zeilenmuster, Probelauf (MVP-1254) und die Seite
 * „Belegvorlagen“ mit Bearbeiten, Zusammenführen und Löschen (MVP-1253).
 */
class IncomingInvoiceTemplateController extends Controller {
    /** Höchstens so viele offene Eingänge prüft ein Probelauf — jeder kostet einen Wortboxen-Lauf. */
    private const TRIAL_LIMIT = 30;

    public function __construct(
        private readonly WordBoxSource $boxes,
        private readonly PageImages $images,
        private readonly TemplateMatcher $matcher,
        private readonly TemplateRecognizer $recognizer,
        private readonly ZoneExtractor $extractor,
        private readonly TemplateMerger $merger,
    ) {}

    // ── Zonen-Editor am Beleg ────────────────────────────────────────────

    public function edit(Document $document): View|RedirectResponse {
        [$incoming] = $this->resolve($document);
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        if ($pdf === null) {
            return redirect()->route('finance.incoming-invoices.show', $document)->with('error', __('Zonen lassen sich nur auf PDF-Belegen zeichnen.'));
        }
        $boxes = $this->boxes->boxesFor($pdf);
        $template = $this->chosenTemplate(request()->query('template'), $incoming, $boxes);
        $pageCount = self::pageCount($boxes);
        $shownPages = PageImages::shownPages($pageCount);
        $pages = $this->images->pages($incoming, $shownPages);
        // Nur Boxen der gezeigten Seiten gehen ins Attribut; Boxen weiterer Seiten blähen es nur auf.
        $visible = array_fill_keys($shownPages, true);
        $shown = array_values(array_filter($boxes, static fn (WordBox $box): bool => isset($visible[$box->page])));
        $config = [
            'pages' => array_map(fn (int $page): array => ['page' => $page, 'url' => route('finance.incoming-invoices.template.page', [$document, $page])], $pages),
            'boxes' => array_map(static fn (WordBox $box): array => $box->toArray(), $shown),
            'zones' => $template?->zoneList() ?? [],
            'fields' => ZoneFields::labels(),
            'scopes' => ZoneFields::scopeLabels(),
            'previewUrl' => route('finance.incoming-invoices.template.preview', $document),
            'trialUrl' => route('finance.incoming-invoices.template.trial', $document),
        ];

        return view('finance.incoming-invoices.template', [
            'document' => $document,
            'incoming' => $incoming,
            'template' => $template,
            'config' => JsonHelper::encode($config, JSON_UNESCAPED_UNICODE),
            'pages' => $pages,
            'pageCount' => $pageCount,
            'fields' => ZoneFields::labels(),
            'suppliers' => $this->suppliers(),
            'templates' => IncomingInvoiceTemplate::query()->with('supplier:id,name')->orderBy('name')->get(),
            'openCount' => $this->openSiblings($incoming)->count(),
        ]);
    }

    public function page(Document $document, int $page): BinaryFileResponse {
        [$incoming] = $this->resolve($document);
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        abort_if($pdf === null, 404);
        abort_unless(in_array($page, PageImages::shownPages(self::pageCount($this->boxes->boxesFor($pdf))), true), 404);
        $path = $this->images->pathFor($incoming, $page);
        abort_if($path === null, 404);

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }

    /**
     * Vorschau einer Zone. Mit `detect` erkennt der Server die Beschriftung im
     * gezeichneten Kästchen als Anker (E2); das Kästchen bleibt, wie es
     * gezeichnet wurde. Sonst wertet er die Zone wie übergeben aus.
     */
    public function preview(Request $request, Document $document): JsonResponse {
        [$incoming] = $this->resolve($document);
        $data = $request->validate([...self::zoneRules(), 'detect' => ['nullable', 'boolean']]);
        $zone = self::normalizeZone($data);
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        $boxes = $pdf === null ? [] : $this->boxes->boxesFor($pdf);
        if ((bool) ($data['detect'] ?? false) && ($zone['anchor'] ?? null) === null) {
            $split = $this->extractor->split($zone, $boxes);
            if ($split['anchor'] !== null && $split['anchor_box'] !== null) {
                $zone = [...$zone, 'anchor' => $split['anchor'], 'anchor_box' => $split['anchor_box'], 'scope' => 'any'];
            }
        }
        $hits = $this->extractor->hits($zone, $boxes);
        $withValue = array_values(array_filter($hits, static fn (array $hit): bool => $hit['value'] !== null));
        $first = $withValue[0] ?? $hits[0] ?? null;

        return response()->json([
            'raw' => $first['raw'] ?? '',
            'value' => $first['value'] ?? null,
            'anchor' => $zone['anchor'] ?? null,
            'anchor_box' => $zone['anchor_box'] ?? null,
            'x' => $zone['x'],
            'y' => $zone['y'],
            'w' => $zone['w'],
            'h' => $zone['h'],
            'scope' => $zone['scope'],
            'pattern' => $zone['pattern'] ?? null,
            'hits' => count($withValue),
            'pages' => array_values(array_unique(array_map(static fn (array $hit): int => $hit['page'], $withValue))),
        ]);
    }

    public function store(Request $request, Document $document): RedirectResponse {
        [$incoming, $actor] = $this->resolve($document);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'zones' => ['required', 'string', 'max:40000'],
            'apply_to_open' => ['nullable', 'boolean'],
            'supplier' => ['nullable', 'string', 'max:64'],
            'template' => ['nullable', 'string', 'max:64'],
        ]);
        $supplierId = $this->supplierIdFrom($data['supplier'] ?? null);
        $zones = self::decodeZones((string) $data['zones']);
        if ($zones === []) {
            return back()->with('error', __('Keine Zone gezeichnet.'));
        }
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        $boxes = $pdf === null ? [] : $this->boxes->boxesFor($pdf);
        $template = $this->chosenTemplate($data['template'] ?? null, $incoming, $boxes);
        if ($template === null) {
            $template = IncomingInvoiceTemplate::query()->create([
                'organization_id' => $incoming->organization_id,
                'supplier_id' => $supplierId ?? $incoming->supplier_id,
                'name' => $data['name'],
                'fingerprint' => TemplateFingerprint::fromBoxes($boxes),
                'zones' => $zones,
                'source' => IncomingInvoiceTemplate::SOURCE_MANUAL,
                'created_by' => $actor->id,
            ]);
        } else {
            // Die Zonenliste des Editors ist der neue Stand der Vorlage — entfernte Zonen sind weg (Befund 2026-10-20);
            // die Kopfform dieses Belegs wird als Variante des Fingerabdrucks gemerkt.
            $template->forceFill([
                'name' => $data['name'],
                'zones' => $zones,
                'supplier_id' => $supplierId ?? $template->supplier_id ?? $incoming->supplier_id,
                'fingerprint' => TemplateFingerprint::withVariant((array) $template->fingerprint, TemplateFingerprint::fromBoxes($boxes)),
            ])->save();
        }
        $template->audit('incoming_einvoice.template_saved', ['source' => IncomingInvoiceTemplate::SOURCE_MANUAL, 'fields' => array_column($zones, 'field')]);

        $applied = $this->recognizer->applyToIncoming($incoming, $template, $actor);
        $others = 0;
        if ((bool) ($data['apply_to_open'] ?? false)) {
            foreach ($this->openSiblings($incoming) as $sibling) {
                try {
                    if ($this->matchesTemplate($sibling, $template) && $this->recognizer->applyToIncoming($sibling, $template, $actor) !== []) {
                        $others++;
                    }
                } catch (\RuntimeException $e) {
                    report($e);
                }
            }
        }
        $message = (string) __('Vorlage „:name“ gespeichert (:fields).', ['name' => $template->name, 'fields' => implode(', ', array_map(ZoneFields::label(...), $applied !== [] ? $applied : array_column($zones, 'field')))]);
        if ($others > 0) {
            $message .= ' ' . trans_choice(':count weiterer offener Eingang neu erkannt.|:count weitere offene Eingänge neu erkannt.', $others, ['count' => $others]);
        }

        return redirect()->route('finance.incoming-invoices.show', $document)->with('success', $message);
    }

    /** Probelauf im Editor (MVP-1254): die gezeichneten, noch ungespeicherten Zonen gegen die offenen Belege derselben Form. */
    public function trial(Request $request, Document $document): JsonResponse {
        [$incoming] = $this->resolve($document);
        $data = $request->validate(['zones' => ['required', 'string', 'max:40000'], 'template' => ['nullable', 'string', 'max:64']]);
        $zones = self::decodeZones((string) $data['zones']);
        if ($zones === []) {
            return response()->json(['total' => 0, 'filled' => 0, 'fields' => [], 'rows' => []]);
        }
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        $boxes = $pdf === null ? [] : $this->boxes->boxesFor($pdf);
        $existing = $this->chosenTemplate($data['template'] ?? null, $incoming, $boxes);
        $fingerprint = $existing->fingerprint ?? [];
        $probe = new IncomingInvoiceTemplate([
            'organization_id' => $incoming->organization_id,
            'supplier_id' => $existing->supplier_id ?? $incoming->supplier_id,
            'name' => $existing->name ?? '',
            'fingerprint' => $fingerprint !== [] ? $fingerprint : TemplateFingerprint::fromBoxes($boxes),
            'zones' => $zones,
        ]);

        return response()->json($this->runTrial($probe, $this->openSiblings($incoming)));
    }

    // ── Seite „Belegvorlagen“ (MVP-1253) ─────────────────────────────────

    public function index(): View {
        $this->authorizeBilling();
        $templates = IncomingInvoiceTemplate::query()->with('supplier:id,name')->orderBy('name')->paginate(50)->withQueryString();
        $ids = $templates->getCollection()->modelKeys();
        $linked = $ids === [] ? collect() : IncomingEInvoice::query()->whereIn('template_id', $ids)
            ->selectRaw('template_id, COUNT(*) AS n')->groupBy('template_id')->pluck('n', 'template_id');
        $latestIds = $ids === [] ? [] : IncomingEInvoice::query()->whereIn('template_id', $ids)
            ->selectRaw('MAX(id) AS id')->groupBy('template_id')->pluck('id')->all();
        $lastIncoming = $latestIds === [] ? collect() : IncomingEInvoice::query()->with('document')->whereIn('id', $latestIds)->get()->keyBy('template_id');

        return view('finance.incoming-invoices.templates.index', [
            'templates' => $templates,
            'linked' => $linked,
            'lastIncoming' => $lastIncoming,
        ]);
    }

    public function editForm(IncomingInvoiceTemplate $template): View {
        $this->authorizeBilling();

        return view('finance.incoming-invoices.templates._form_dialog', ['template' => $template, 'suppliers' => $this->suppliers()]);
    }

    public function update(Request $request, IncomingInvoiceTemplate $template): RedirectResponse {
        $this->authorizeBilling();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'supplier' => ['nullable', 'string', 'max:64'],
        ]);
        $before = ['name' => $template->name, 'supplier_id' => $template->supplier_id];
        $template->forceFill(['name' => $data['name'], 'supplier_id' => $this->supplierIdFrom($data['supplier'] ?? null)])->save();
        $template->audit('incoming_einvoice.template_updated', ['before' => $before, 'after' => ['name' => $template->name, 'supplier_id' => $template->supplier_id]]);

        return redirect()->route('finance.incoming-invoices.templates.index')->with('success', __('Vorlage „:name“ gespeichert.', ['name' => $template->name]));
    }

    public function mergeForm(IncomingInvoiceTemplate $template): View {
        $this->authorizeBilling();

        return view('finance.incoming-invoices.templates._merge_dialog', [
            'template' => $template,
            'targets' => IncomingInvoiceTemplate::query()->with('supplier:id,name')->whereKeyNot($template->getKey())->orderBy('name')->get(),
        ]);
    }

    public function merge(Request $request, IncomingInvoiceTemplate $template): RedirectResponse {
        $actor = $this->authorizeBilling();
        $data = $request->validate(['target' => ['required', 'string', 'max:64']]);
        $target = (new IncomingInvoiceTemplate)->resolveRouteBinding((string) $data['target']);
        if (! $target instanceof IncomingInvoiceTemplate || $target->is($template)) {
            throw ValidationException::withMessages(['target' => (string) __('Bitte eine andere Vorlage als Ziel wählen.')]);
        }
        try {
            $result = $this->merger->merge($template, $target, $actor);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['target' => (string) __('Bitte eine andere Vorlage als Ziel wählen.')]);
        }
        $message = (string) __('Vorlage „:source“ ist in „:target“ aufgegangen.', ['source' => $template->name, 'target' => $target->name]);
        if ($result['moved'] > 0) {
            $message .= ' ' . trans_choice(':count Eingang umgehängt.|:count Eingänge umgehängt.', $result['moved'], ['count' => $result['moved']]);
        }
        if ($result['fields'] !== []) {
            $message .= ' ' . __('Übernommene Felder: :fields.', ['fields' => implode(', ', array_map(ZoneFields::label(...), $result['fields']))]);
        }

        return redirect()->route('finance.incoming-invoices.templates.index')->with('success', $message);
    }

    /** Probelauf von der Liste: die gespeicherte Vorlage gegen die offenen Belege ihrer Form, ohne zu schreiben. */
    public function trialFromList(IncomingInvoiceTemplate $template): RedirectResponse {
        $user = $this->authorizeBilling();
        $candidates = $this->openCandidates((int) $user->organization_id, $template->supplier_id)
            ->filter(fn (IncomingEInvoice $incoming): bool => $this->matchesTemplate($incoming, $template))->values();
        $result = $this->runTrial($template, $candidates);
        if ($result['total'] === 0) {
            return redirect()->route('finance.incoming-invoices.templates.index')->with('info', __('Probelauf „:name“: keine offenen Belege dieser Form.', ['name' => $template->name]));
        }
        $fields = [];
        foreach ($result['fields'] as $field => $count) {
            $fields[] = ZoneFields::label($field) . ' ' . $count;
        }

        return redirect()->route('finance.incoming-invoices.templates.index')->with('success', __('Probelauf „:name“: :filled von :total offenen Belegen gefüllt (:fields).', [
            'name' => $template->name,
            'filled' => $result['filled'],
            'total' => $result['total'],
            'fields' => $fields === [] ? '—' : implode(', ', $fields),
        ]));
    }

    public function destroy(IncomingInvoiceTemplate $template): RedirectResponse {
        $this->authorizeBilling();
        $template->audit('incoming_einvoice.template_deleted', ['name' => $template->name]);
        $template->delete();

        return redirect()->route('finance.incoming-invoices.templates.index')->with('success', __('Vorlage gelöscht.'));
    }

    // ── Helfer ───────────────────────────────────────────────────────────

    private function authorizeBilling(): User {
        $user = $this->authUser();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }

    /** @return array{0: IncomingEInvoice, 1: User} */
    private function resolve(Document $document): array {
        Gate::authorize('view', $document);
        abort_unless($document->document_type === DocumentType::Invoice, 404);
        $user = $this->authorizeBilling();
        $incoming = IncomingEInvoice::query()->where('document_id', $document->id)->first();
        abort_if($incoming === null || $incoming->recognition === IncomingInvoiceRecognition::Structured, 404);

        return [$incoming, $user];
    }

    /**
     * Vorlage, die der Editor bearbeitet: ausdrücklich gewählt (Sqid), ausdrücklich neu (`new`) oder — ohne Angabe —
     * die am Beleg hängende bzw. die ähnlichste (Befund 2026-10-20: das Raten allein legte Doppelvorlagen an).
     *
     * @param  list<WordBox>  $boxes
     */
    private function chosenTemplate(mixed $choice, IncomingEInvoice $incoming, array $boxes): ?IncomingInvoiceTemplate {
        $choice = is_string($choice) ? trim($choice) : '';
        if ($choice === 'new') {
            return null;
        }
        if ($choice !== '') {
            $chosen = (new IncomingInvoiceTemplate)->resolveRouteBinding($choice);
            if ($chosen instanceof IncomingInvoiceTemplate) {
                return $chosen;
            }
        }

        return $this->templateFor($incoming, $boxes);
    }

    /** @param  list<WordBox>  $boxes */
    private function templateFor(IncomingEInvoice $incoming, array $boxes): ?IncomingInvoiceTemplate {
        return $incoming->template ?? ($this->matcher->match((int) $incoming->organization_id, $boxes, $incoming->seller_vat_id, $incoming->supplier_id)['template'] ?? null);
    }

    /** Trifft die gespeicherte Vorlage den Eingang (Lieferant, USt-IdNr. oder Fingerabdruck)? */
    private function matchesTemplate(IncomingEInvoice $incoming, IncomingInvoiceTemplate $template): bool {
        if ($incoming->template_id !== null && (int) $incoming->template_id === (int) $template->id) {
            return true;
        }
        $path = TemplateRecognizer::pdfPathFor($incoming);
        if ($path === null) {
            return false;
        }
        $boxes = $this->boxes->boxesFor($path);
        if ($template->exists) {
            $match = $this->matcher->match((int) $incoming->organization_id, $boxes, $incoming->seller_vat_id, $incoming->supplier_id);

            return $match !== null && (int) $match['template']->id === (int) $template->id;
        }

        return ($template->supplier_id !== null && (int) $incoming->supplier_id === (int) $template->supplier_id)
            || TemplateFingerprint::similarity(TemplateFingerprint::fromBoxes($boxes), (array) $template->fingerprint) >= TemplateFingerprint::MIN_SIMILARITY;
    }

    /**
     * @param  Collection<int, IncomingEInvoice>  $incomings
     * @return array{total: int, filled: int, fields: array<string, int>, rows: list<array{title: string, fields: array<string, string>}>}
     */
    private function runTrial(IncomingInvoiceTemplate $template, Collection $incomings): array {
        $rows = [];
        $filled = 0;
        $fields = [];
        $total = 0;
        foreach ($incomings->take(self::TRIAL_LIMIT) as $incoming) {
            if (! $template->exists && ! $this->matchesTemplate($incoming, $template)) {
                continue;
            }
            $total++;
            $values = $this->recognizer->trial($incoming, $template);
            if ($values !== []) {
                $filled++;
            }
            foreach (array_keys($values) as $field) {
                $fields[$field] = ($fields[$field] ?? 0) + 1;
            }
            $labelled = [];
            foreach ($values as $field => $value) {
                $labelled[$field] = $value;
            }
            $rows[] = ['title' => (string) ($incoming->document->title ?? $incoming->invoice_number ?? '#' . $incoming->id), 'fields' => $labelled];
        }

        return ['total' => $total, 'filled' => $filled, 'fields' => $fields, 'rows' => $rows];
    }

    /** @return Collection<int, IncomingEInvoice> offene unstrukturierte Eingänge der Organisation, andere Belege */
    private function openSiblings(IncomingEInvoice $incoming): Collection {
        return $this->openCandidates((int) $incoming->organization_id, $incoming->supplier_id)->reject(fn (IncomingEInvoice $other): bool => $other->is($incoming))->values();
    }

    /** @return Collection<int, IncomingEInvoice> offene unstrukturierte Eingänge der Organisation, bei Lieferant nur dessen und die ohne */
    private function openCandidates(int $organizationId, ?int $supplierId): Collection {
        return IncomingEInvoice::query()->withoutGlobalScopes()->with('document')
            ->where('organization_id', $organizationId)
            ->where('recognition', '!=', IncomingInvoiceRecognition::Structured->value)
            ->where('status', '!=', IncomingEInvoiceStatus::Rejected->value)
            ->whereNull('transferred_at')
            ->when($supplierId !== null, static fn ($q) => $q->where(static fn ($w) => $w->where('supplier_id', $supplierId)->orWhereNull('supplier_id')))
            ->orderBy('id')
            ->get();
    }

    /** Lieferant aus dem Sqid des Formulars (Organisation über den globalen Scope); leer = kein Lieferant. */
    private function supplierIdFrom(mixed $sqid): ?int {
        if (! is_string($sqid) || trim($sqid) === '') {
            return null;
        }
        $supplier = (new Supplier)->resolveRouteBinding(trim($sqid));
        if (! $supplier instanceof Supplier) {
            throw ValidationException::withMessages(['supplier' => (string) __('Der gewählte Lieferant ist unbekannt.')]);
        }

        return (int) $supplier->getKey();
    }

    /** @return Collection<int, Supplier> */
    private function suppliers(): Collection {
        return Supplier::query()->withoutCollective()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'vat_id']);
    }

    /** @param  list<WordBox>  $boxes */
    private static function pageCount(array $boxes): int {
        $max = 1;
        foreach ($boxes as $box) {
            $max = max($max, $box->page);
        }

        return $max;
    }

    /** @return array<string, list<mixed>> */
    private static function zoneRules(): array {
        return [
            'field' => ['required', Rule::in(array_keys(ZoneFields::FIELDS))],
            'page' => ['required', 'integer', 'min:1', 'max:999'],
            'x' => ['required', 'numeric', 'min:0', 'max:1'],
            'y' => ['required', 'numeric', 'min:0', 'max:1'],
            'w' => ['required', 'numeric', 'min:0', 'max:1'],
            'h' => ['required', 'numeric', 'min:0', 'max:1'],
            'anchor' => ['nullable', 'string', 'max:191'],
            'anchor_box' => ['nullable', 'array'],
            'anchor_box.x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'anchor_box.y' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'anchor_box.w' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'anchor_box.h' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'scope' => ['nullable', Rule::in(ZoneFields::SCOPES)],
            'pattern' => ['nullable', 'string', 'max:' . ZoneExtractor::PATTERN_MAX_LENGTH],
        ];
    }

    /**
     * Zone aus Eingaben: Koordinaten geklemmt, Anker nur mit Lage, Bereich mit Vorgabe, Zeilenmuster geprüft (E3).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function normalizeZone(array $raw): array {
        $clamp = static fn (mixed $value): float => max(0.0, min(1.0, (float) $value));
        $anchor = trim((string) ($raw['anchor'] ?? ''));
        $box = is_array($raw['anchor_box'] ?? null) && isset($raw['anchor_box']['x'], $raw['anchor_box']['y'])
            ? ['x' => $clamp($raw['anchor_box']['x']), 'y' => $clamp($raw['anchor_box']['y']), 'w' => $clamp($raw['anchor_box']['w'] ?? 0), 'h' => $clamp($raw['anchor_box']['h'] ?? 0)]
            : null;
        if ($anchor === '' || $box === null) {
            $anchor = '';
            $box = null;
        }
        $pattern = trim((string) ($raw['pattern'] ?? ''));
        if ($pattern !== '' && ! ZoneExtractor::isValidPattern($pattern)) {
            throw ValidationException::withMessages(['pattern' => (string) __('Das Zeilenmuster ist kein gültiger regulärer Ausdruck (höchstens :max Zeichen, erste Klammergruppe ist der Wert).', ['max' => ZoneExtractor::PATTERN_MAX_LENGTH])]);
        }
        $scope = (string) ($raw['scope'] ?? '');
        if (! in_array($scope, ZoneFields::SCOPES, true)) {
            $scope = $anchor !== '' ? 'any' : 'page';
        }
        $field = (string) ($raw['field'] ?? '');

        return [
            'field' => $field,
            'page' => max(1, (int) ($raw['page'] ?? 1)),
            'x' => $clamp($raw['x'] ?? 0),
            'y' => $clamp($raw['y'] ?? 0),
            'w' => $clamp($raw['w'] ?? 0),
            'h' => $clamp($raw['h'] ?? 0),
            'parse' => ZoneFields::parseOf($field),
            'anchor' => $anchor === '' ? null : mb_substr($anchor, 0, 191),
            'anchor_box' => $box,
            'scope' => $scope,
            'pattern' => $pattern === '' ? null : $pattern,
        ];
    }

    /**
     * Zonen aus dem JSON des Editors: nur bekannte Felder, je Feld die letzte; gezeichnete gelten als bestätigt.
     *
     * @return list<array<string, mixed>>
     */
    private static function decodeZones(string $json): array {
        $decoded = JsonHelper::decode($json);
        $byField = [];
        foreach (is_array($decoded) ? $decoded : [] as $raw) {
            if (! is_array($raw) || ! isset(ZoneFields::FIELDS[(string) ($raw['field'] ?? '')])) {
                continue;
            }
            $zone = self::normalizeZone($raw);
            if ($zone['w'] <= 0.0 || $zone['h'] <= 0.0) {
                continue;
            }
            // Gelernte Zonen behalten Quelle und Bestätigung, wenn der Editor sie unverändert zurückgibt.
            $learned = ($raw['source'] ?? '') === IncomingInvoiceTemplate::SOURCE_LEARNED;
            $byField[$zone['field']] = $zone + [
                'source' => $learned ? IncomingInvoiceTemplate::SOURCE_LEARNED : IncomingInvoiceTemplate::SOURCE_MANUAL,
                'confirmed' => $learned ? (bool) ($raw['confirmed'] ?? false) : true,
            ];
        }

        return array_values($byField);
    }
}
