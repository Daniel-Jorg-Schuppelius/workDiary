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
use App\Services\Invoicing\Recognition\Contracts\WordBoxSource;
use App\Services\Invoicing\Recognition\{PageImages, TemplateFingerprint, TemplateMatcher, TemplateRecognizer, ZoneExtractor, ZoneFields};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PDFToolkit\Entities\WordBox;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Zonen-Editor (Phase 151, MVP-1220): Seitenbild, Wortboxen als Overlay,
 * Zonen je Feld ziehen, Wert prüfen, als Vorlage des Lieferanten speichern und
 * auf die offenen Eingänge derselben Form anwenden.
 */
class IncomingInvoiceTemplateController extends Controller {
    public function __construct(
        private readonly WordBoxSource $boxes,
        private readonly PageImages $images,
        private readonly TemplateMatcher $matcher,
        private readonly TemplateRecognizer $recognizer,
        private readonly ZoneExtractor $extractor,
    ) {}

    public function edit(Document $document): View|RedirectResponse {
        [$incoming] = $this->resolve($document);
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        if ($pdf === null) {
            return redirect()->route('finance.incoming-invoices.show', $document)->with('error', __('Zonen lassen sich nur auf PDF-Belegen zeichnen.'));
        }
        $boxes = $this->boxes->boxesFor($pdf);
        $template = $incoming->template ?? ($this->matcher->match((int) $incoming->organization_id, $boxes, $incoming->seller_vat_id, $incoming->supplier_id)['template'] ?? null);
        $pages = $this->images->pages($incoming);
        // Der Editor zeigt höchstens drei Seiten; Boxen weiterer Seiten blähen nur das Attribut auf.
        $shown = array_values(array_filter($boxes, static fn (WordBox $box): bool => $box->page <= PageImages::MAX_PAGES));
        $config = [
            'pages' => array_map(fn (int $page): array => ['page' => $page, 'url' => route('finance.incoming-invoices.template.page', [$document, $page])], $pages),
            'boxes' => array_map(static fn (WordBox $box): array => $box->toArray(), $shown),
            'zones' => $template?->zoneList() ?? [],
            'fields' => ZoneFields::labels(),
            'previewUrl' => route('finance.incoming-invoices.template.preview', $document),
        ];

        return view('finance.incoming-invoices.template', [
            'document' => $document,
            'incoming' => $incoming,
            'template' => $template,
            'config' => JsonHelper::encode($config, JSON_UNESCAPED_UNICODE),
            'pages' => $pages,
            'fields' => ZoneFields::labels(),
            'openCount' => $this->openSiblings($incoming)->count(),
        ]);
    }

    public function page(Document $document, int $page): BinaryFileResponse {
        [$incoming] = $this->resolve($document);
        $path = $this->images->pathFor($incoming, $page);
        abort_if($path === null, 404);

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function preview(Request $request, Document $document): JsonResponse {
        [$incoming] = $this->resolve($document);
        $zone = $request->validate([
            'field' => ['required', Rule::in(array_keys(ZoneFields::FIELDS))],
            'page' => ['required', 'integer', 'min:1', 'max:' . PageImages::MAX_PAGES],
            'x' => ['required', 'numeric', 'min:0', 'max:1'],
            'y' => ['required', 'numeric', 'min:0', 'max:1'],
            'w' => ['required', 'numeric', 'min:0', 'max:1'],
            'h' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        $boxes = $pdf === null ? [] : $this->boxes->boxesFor($pdf);
        $zone['parse'] = ZoneFields::parseOf((string) $zone['field']);

        return response()->json([
            'raw' => $this->extractor->rawText($zone, $boxes),
            'value' => $this->extractor->extract($zone, $boxes),
        ]);
    }

    public function store(Request $request, Document $document): RedirectResponse {
        [$incoming, $actor] = $this->resolve($document);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'zones' => ['required', 'string', 'max:20000'],
            'apply_to_open' => ['nullable', 'boolean'],
        ]);
        $decoded = JsonHelper::decode((string) $data['zones']);
        $zones = [];
        foreach (is_array($decoded) ? $decoded : [] as $zone) {
            if (! is_array($zone) || ! isset(ZoneFields::FIELDS[(string) ($zone['field'] ?? '')])) {
                continue;
            }
            $zones[] = [
                'field' => (string) $zone['field'],
                'page' => max(1, min(PageImages::MAX_PAGES, (int) ($zone['page'] ?? 1))),
                'x' => max(0.0, min(1.0, (float) ($zone['x'] ?? 0))),
                'y' => max(0.0, min(1.0, (float) ($zone['y'] ?? 0))),
                'w' => max(0.0, min(1.0, (float) ($zone['w'] ?? 0))),
                'h' => max(0.0, min(1.0, (float) ($zone['h'] ?? 0))),
                'parse' => ZoneFields::parseOf((string) $zone['field']),
                'source' => IncomingInvoiceTemplate::SOURCE_MANUAL,
                'confirmed' => true,
            ];
        }
        if ($zones === []) {
            return back()->with('error', __('Keine Zone gezeichnet.'));
        }
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        $boxes = $pdf === null ? [] : $this->boxes->boxesFor($pdf);
        $template = $incoming->template ?? ($this->matcher->match((int) $incoming->organization_id, $boxes, $incoming->seller_vat_id, $incoming->supplier_id)['template'] ?? null);
        if ($template === null) {
            $template = IncomingInvoiceTemplate::query()->create([
                'organization_id' => $incoming->organization_id,
                'supplier_id' => $incoming->supplier_id,
                'name' => $data['name'],
                'fingerprint' => TemplateFingerprint::fromBoxes($boxes),
                'zones' => $zones,
                'source' => IncomingInvoiceTemplate::SOURCE_MANUAL,
                'created_by' => $actor->id,
            ]);
        } else {
            // Gezeichnete Zonen ersetzen gelernte desselben Feldes; andere gelernte bleiben.
            $drawn = array_fill_keys(array_map(static fn (array $z): string => $z['field'], $zones), true);
            $kept = array_values(array_filter($template->zoneList(), static fn (array $z): bool => ! isset($drawn[(string) ($z['field'] ?? '')])));
            $template->forceFill([
                'name' => $data['name'],
                'zones' => [...$kept, ...$zones],
                'supplier_id' => $template->supplier_id ?? $incoming->supplier_id,
                'fingerprint' => $template->fingerprint !== [] ? $template->fingerprint : TemplateFingerprint::fromBoxes($boxes),
            ])->save();
        }
        $template->audit('incoming_einvoice.template_saved', ['source' => IncomingInvoiceTemplate::SOURCE_MANUAL, 'fields' => array_keys($drawn ?? array_fill_keys(array_column($zones, 'field'), true))]);

        $applied = $this->recognizer->applyToIncoming($incoming, $template, $actor);
        $others = 0;
        if ((bool) ($data['apply_to_open'] ?? false)) {
            foreach ($this->openSiblings($incoming) as $sibling) {
                try {
                    $path = TemplateRecognizer::pdfPathFor($sibling);
                    if ($path === null) {
                        continue;
                    }
                    $match = $this->matcher->match((int) $sibling->organization_id, $this->boxes->boxesFor($path), $sibling->seller_vat_id, $sibling->supplier_id);
                    if ($match !== null && (int) $match['template']->id === (int) $template->id && $this->recognizer->applyToIncoming($sibling, $template, $actor) !== []) {
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

    /** @return array{0: IncomingEInvoice, 1: User} */
    private function resolve(Document $document): array {
        Gate::authorize('view', $document);
        abort_unless($document->document_type === DocumentType::Invoice, 404);
        $user = $this->authUser();
        abort_unless($user->canManageBilling(), 403);
        $incoming = IncomingEInvoice::query()->where('document_id', $document->id)->first();
        abort_if($incoming === null || $incoming->recognition === IncomingInvoiceRecognition::Structured, 404);

        return [$incoming, $user];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, IncomingEInvoice> offene unstrukturierte Eingänge der Organisation, andere Belege */
    private function openSiblings(IncomingEInvoice $incoming): \Illuminate\Database\Eloquent\Collection {
        return IncomingEInvoice::query()->withoutGlobalScopes()
            ->where('organization_id', $incoming->organization_id)
            ->whereKeyNot($incoming->getKey())
            ->where('recognition', '!=', IncomingInvoiceRecognition::Structured->value)
            ->where('status', '!=', IncomingEInvoiceStatus::Rejected->value)
            ->whereNull('transferred_at')
            ->when($incoming->supplier_id !== null, static fn ($q) => $q->where(static fn ($w) => $w->where('supplier_id', $incoming->supplier_id)->orWhereNull('supplier_id')))
            ->orderBy('id')
            ->get();
    }
}
