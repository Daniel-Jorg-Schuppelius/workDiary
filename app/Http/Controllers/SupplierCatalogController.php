<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Procurement\{CatalogItemStatus, CatalogSourceFormat};
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\SaveSupplierCatalogSourceRequest;
use App\Models\{Article, ArticleVariant, PricingChangeAlert, Supplier, SupplierCatalogImport, SupplierCatalogItem, SupplierCatalogSource, Warehouse};
use App\Services\Procurement\{CatalogArticleAdopter, CatalogFetchService, CatalogImportDispatcher, CatalogLinkService, PriceSuggestionService, ShopinfoParser};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Lieferantenkataloge (Feature 050, MVP-091/092): Katalogquellen verwalten und
 * CSV-Preislisten mit Mapping importieren. Der interne Artikelstamm bleibt
 * unberührt — Verknüpfung folgt in MVP-093. Modul-Gating über
 * `supplier-catalogs.*` → module.lager; lesen mit inventory.viewAny, verwalten
 * mit inventory.post.
 */
class SupplierCatalogController extends Controller {
    use ResolvesCurrentOrganization;

    /** Pflicht- und optionale Mapping-Zielfelder der Importmaske. */
    private const MAPPING_FIELDS = [
        'external_no', 'external_no_fallback', 'name', 'purchase_price', 'list_price', 'currency', 'gtin',
        'manufacturer_no', 'manufacturer', 'category', 'availability', 'lead_time_days',
    ];

    public function index(): View {
        $this->canView();

        $sources = SupplierCatalogSource::query()
            ->with('supplier')
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(25);

        return view('supplier-catalogs.index', [
            'sources' => $sources,
            'openAlerts' => PricingChangeAlert::query()->where('status', PricingChangeAlert::STATUS_OPEN)->count(),
        ]);
    }

    /** Übersicht offener Kalkulationswarnungen (MVP-094). */
    public function alerts(): View {
        $this->canView();

        return view('supplier-catalogs.alerts', [
            'alerts' => PricingChangeAlert::query()
                ->with(['article:id,name', 'supplier:id,name'])
                ->where('status', PricingChangeAlert::STATUS_OPEN)
                ->orderByDesc('id')
                ->paginate(50),
        ]);
    }

    public function acknowledgeAlert(PricingChangeAlert $alert): RedirectResponse {
        $this->canManage();
        abort_unless($alert->organization_id === $this->currentOrganization()->id, 404);

        $alert->forceFill([
            'status' => PricingChangeAlert::STATUS_ACKNOWLEDGED,
            'acknowledged_by' => Auth::id(),
            'acknowledged_at' => now(),
        ])->save();

        return back()->with('success', __('procurement.alert.flash.acknowledged'));
    }

    public function create(): View {
        $this->canManage();

        return view('supplier-catalogs._form_dialog', [
            'isDialog' => true,
            'suppliers' => Supplier::query()->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function store(SaveSupplierCatalogSourceRequest $request): RedirectResponse {
        $this->canManage();
        $data = $request->validated();

        $source = SupplierCatalogSource::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'supplier_id' => (int) $data['supplier'],
            'name' => $data['name'],
            'format' => $data['format'],
            'source_type' => $data['source_type'] ?? 'upload',
            'delimiter' => $data['delimiter'],
            'decimal_separator' => $data['decimal_separator'],
            'encoding' => $data['encoding'],
            'has_header' => (bool) ($data['has_header'] ?? true),
            'sheet_name' => ($data['sheet_name'] ?? '') !== '' ? $data['sheet_name'] : null,
            'expected_customer_no' => ($data['expected_customer_no'] ?? '') !== '' ? $data['expected_customer_no'] : null,
            'remote_url' => $data['remote_url'] ?? null,
            'remote_host' => $data['remote_host'] ?? null,
            'remote_port' => $data['remote_port'] ?? null,
            'remote_path' => $data['remote_path'] ?? null,
            'remote_username' => $data['remote_username'] ?? null,
            'remote_password' => ($data['remote_password'] ?? '') !== '' ? $data['remote_password'] : null,
            'fetch_interval_minutes' => $data['fetch_interval_minutes'] ?? null,
            'punchout_url' => $data['punchout_url'] ?? null,
            'punchout_username' => $data['punchout_username'] ?? null,
            'punchout_password' => ($data['punchout_password'] ?? '') !== '' ? $data['punchout_password'] : null,
        ]);

        return redirect()->route('supplier-catalogs.show', $source)
            ->with('success', __('procurement.catalog.flash.source_created'));
    }

    public function edit(SupplierCatalogSource $supplierCatalog): View {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);

        return view('supplier-catalogs._form_dialog', [
            'isDialog' => true,
            'source' => $supplierCatalog,
            'suppliers' => Supplier::query()->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function update(SaveSupplierCatalogSourceRequest $request, SupplierCatalogSource $supplierCatalog): RedirectResponse {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);
        $data = $request->validated();

        $supplierCatalog->fill([
            'supplier_id' => (int) $data['supplier'],
            'name' => $data['name'],
            'format' => $data['format'],
            'source_type' => $data['source_type'] ?? 'upload',
            'delimiter' => $data['delimiter'],
            'decimal_separator' => $data['decimal_separator'],
            'encoding' => $data['encoding'],
            'has_header' => (bool) ($data['has_header'] ?? false),
            'sheet_name' => ($data['sheet_name'] ?? '') !== '' ? $data['sheet_name'] : null,
            'expected_customer_no' => ($data['expected_customer_no'] ?? '') !== '' ? $data['expected_customer_no'] : null,
            'remote_url' => $data['remote_url'] ?? null,
            'remote_host' => $data['remote_host'] ?? null,
            'remote_port' => $data['remote_port'] ?? null,
            'remote_path' => $data['remote_path'] ?? null,
            'remote_username' => $data['remote_username'] ?? null,
            'fetch_interval_minutes' => $data['fetch_interval_minutes'] ?? null,
            'punchout_url' => $data['punchout_url'] ?? null,
            'punchout_username' => $data['punchout_username'] ?? null,
        ]);
        // Passwörter nur ersetzen, wenn neue angegeben wurden (sonst bestehende behalten).
        if (($data['remote_password'] ?? '') !== '') {
            $supplierCatalog->remote_password = $data['remote_password'];
        }
        if (($data['punchout_password'] ?? '') !== '') {
            $supplierCatalog->punchout_password = $data['punchout_password'];
        }
        $supplierCatalog->save();

        return redirect()->route('supplier-catalogs.show', $supplierCatalog)
            ->with('success', __('procurement.catalog.flash.updated'));
    }

    public function destroy(SupplierCatalogSource $supplierCatalog): RedirectResponse {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);
        $supplierCatalog->delete();

        return redirect()->route('supplier-catalogs.index')->with('success', __('procurement.catalog.flash.deleted'));
    }

    public function toggleActive(SupplierCatalogSource $supplierCatalog): RedirectResponse {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);
        $supplierCatalog->forceFill(['active' => ! $supplierCatalog->active])->save();

        return back()->with('success', __('procurement.catalog.flash.toggled'));
    }

    public function show(Request $request, SupplierCatalogSource $supplierCatalog): View {
        $this->canView();

        $status = (string) $request->input('status', 'all');
        $items = $supplierCatalog->items()
            ->with('article:id,name')
            ->withCount('priceTiers')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('external_no')
            ->paginate(50)
            ->withQueryString();

        // Verkaufspreisvorschläge je Artikel (MVP-095) für die aktuelle Seite.
        $pricing = app(PriceSuggestionService::class);
        $suggestions = [];
        foreach ($items as $item) {
            $suggestion = $pricing->suggestForItem($item);
            if ($suggestion !== null) {
                $suggestions[$item->id] = $suggestion;
            }
        }

        // Gespeicherte attr.*-Mappings als {code, column}-Zeilen für die Maske.
        $attrMapping = [];
        foreach ((array) ($supplierCatalog->mapping ?? []) as $target => $column) {
            if (str_starts_with((string) $target, 'attr.')) {
                $attrMapping[] = ['code' => substr((string) $target, 5), 'column' => (string) $column];
            }
        }

        return view('supplier-catalogs.show', [
            'source' => $supplierCatalog->load('supplier'),
            'imports' => $supplierCatalog->imports()->limit(10)->get(),
            'items' => $items,
            'suggestions' => $suggestions,
            'status' => $status,
            'statuses' => CatalogItemStatus::cases(),
            'mappingFields' => self::MAPPING_FIELDS,
            'attrMapping' => $attrMapping,
            'canManage' => Auth::user()?->can(P::InventoryPost->value) ?? false,
            'approvalMode' => $this->currentOrganization()->pricingApprovalMode(),
            'warehouses' => $supplierCatalog->hasPunchout() ? Warehouse::query()->orderBy('name')->get() : collect(),
        ]);
    }

    /** Importiert eine hochgeladene Katalogdatei (CSV/XLSX mit Mapping, DATANORM oder BMEcat). */
    public function import(Request $request, SupplierCatalogSource $supplierCatalog): RedirectResponse {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);

        $request->validate([
            'catalog_csv' => ['required', 'file', 'max:16384'],
            'import_mode' => ['nullable', 'in:auto,snapshot,delta'],
        ]);
        $content = (string) file_get_contents((string) $request->file('catalog_csv')?->getRealPath());

        $mapping = $this->mappingFromRequest($request);
        if (in_array($supplierCatalog->format, [CatalogSourceFormat::Csv, CatalogSourceFormat::Xlsx], true) && $mapping !== []) {
            // Mapping merken — für späteren automatischen Abruf wiederverwendbar.
            $supplierCatalog->forceFill(['mapping' => $mapping])->save();
        }

        $mode = $request->string('import_mode')->toString();

        return $this->runImport($supplierCatalog, $content, $mapping, in_array($mode, ['snapshot', 'delta'], true) ? $mode : null);
    }

    /** Ruft die Katalogdatei einer Remote-Quelle ab und importiert sie (MVP-091, „Später"). */
    public function fetchRemote(SupplierCatalogSource $supplierCatalog, CatalogFetchService $fetch): RedirectResponse {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);

        try {
            $content = $fetch->fetch($supplierCatalog);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        /** @var array<string, string> $mapping */
        $mapping = $supplierCatalog->mapping ?? [];

        return $this->runImport($supplierCatalog, $content, $mapping);
    }

    /**
     * Führt den formatabhängigen Import aus und liefert die Flash-Antwort.
     *
     * @param  array<string, string>  $mapping
     */
    private function runImport(SupplierCatalogSource $source, string $content, array $mapping, ?string $datanormMode = null): RedirectResponse {
        try {
            $summary = app(CatalogImportDispatcher::class)->run($source, $content, $mapping, SupplierCatalogImport::TRIGGER_MANUAL, $datanormMode);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.catalog.flash.imported', [
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'price_changed' => $summary['price_changed'],
            'discontinued' => $summary['discontinued'],
        ]));
    }

    /** Modal-Formular zur Verknüpfung eines Katalogartikels mit einem internen Artikel (MVP-093). */
    public function linkForm(SupplierCatalogItem $catalogItem): View {
        $this->canManage();
        $this->assertOrg($catalogItem);

        return view('supplier-catalogs._link_dialog', [
            'item' => $catalogItem->load('source'),
            'articles' => Article::query()->where('purchasable', true)->orderBy('name')->limit(500)->get(),
        ]);
    }

    public function link(Request $request, SupplierCatalogItem $catalogItem, CatalogLinkService $links): RedirectResponse {
        $this->canManage();
        $this->assertOrg($catalogItem);
        $data = $request->validate([
            'article' => ['required', 'string'],
            'variant' => ['nullable', 'string'],
        ]);

        $articleId = app(SqidEncoder::class)->decode(Article::class, (string) $data['article']);
        $article = $articleId !== null ? Article::query()->find($articleId) : null;
        if (! $article instanceof Article) {
            return back()->with('error', __('procurement.flash.unknown_article'));
        }

        $variant = null;
        if (! empty($data['variant'])) {
            $variantId = app(SqidEncoder::class)->decode(ArticleVariant::class, (string) $data['variant']);
            $variant = $variantId !== null ? ArticleVariant::query()->find($variantId) : null;
        }

        try {
            $links->link($catalogItem, $article, $variant);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.catalog.flash.linked'));
    }

    public function unlink(SupplierCatalogItem $catalogItem, CatalogLinkService $links): RedirectResponse {
        $this->canManage();
        $this->assertOrg($catalogItem);
        $links->unlink($catalogItem);

        return back()->with('success', __('procurement.catalog.flash.unlinked'));
    }

    /** Auto-Vorschlag eines internen Artikels anhand der EAN/GTIN (MVP-093). */
    public function propose(SupplierCatalogItem $catalogItem, CatalogLinkService $links): RedirectResponse {
        $this->canManage();
        $this->assertOrg($catalogItem);
        $article = $links->propose($catalogItem);

        if ($article === null) {
            return back()->with('error', __('procurement.catalog.flash.no_match'));
        }

        return back()->with('success', __('procurement.catalog.flash.proposed', ['article' => $article->name]));
    }

    /** Modal: Zusammenfassung vor der Massen-Übernahme in den Artikelstamm (MVP-541). */
    public function adoptForm(SupplierCatalogSource $supplierCatalog, CatalogArticleAdopter $adopter): View {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);

        return view('supplier-catalogs._adopt_dialog', [
            'isDialog' => true,
            'source' => $supplierCatalog,
            'counts' => $adopter->countAdoptable($supplierCatalog),
        ]);
    }

    /** Massen-Übernahme: alle unverknüpften Tarif-Gruppen der Quelle (MVP-541). */
    public function adopt(SupplierCatalogSource $supplierCatalog, CatalogArticleAdopter $adopter): RedirectResponse {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);

        return $this->adoptResponse($adopter->adoptSource($supplierCatalog));
    }

    /** Einzel-Übernahme: die komplette Tarif-Gruppe des Items (MVP-541). */
    public function adoptItem(SupplierCatalogItem $catalogItem, CatalogArticleAdopter $adopter): RedirectResponse {
        $this->canManage();
        $this->assertOrg($catalogItem);

        /** @var SupplierCatalogSource $source */
        $source = $catalogItem->source()->firstOrFail();

        return $this->adoptResponse($adopter->adoptGroup($source, $catalogItem));
    }

    /**
     * @param  array{articles: int, variants: int, linked: int, skipped: int, errors: list<string>}  $summary
     */
    private function adoptResponse(array $summary): RedirectResponse {
        if ($summary['linked'] === 0 && $summary['skipped'] === 0) {
            return back()->with('error', __('procurement.catalog.adopt.error.nothing'));
        }

        $message = (string) __('procurement.catalog.adopt.flash.adopted', [
            'articles' => $summary['articles'],
            'variants' => $summary['variants'],
            'linked' => $summary['linked'],
            'skipped' => $summary['skipped'],
        ]);
        if ($summary['errors'] !== []) {
            $message .= ' ' . implode(' ', array_slice($summary['errors'], 0, 3));
        }

        return back()->with($summary['linked'] > 0 ? 'success' : 'error', $message);
    }

    /** Übernimmt den vorgeschlagenen Verkaufspreis in den verknüpften Artikel (MVP-095, Freigabe). */
    public function applyPrice(SupplierCatalogItem $catalogItem, PriceSuggestionService $pricing): RedirectResponse {
        $this->canManage();
        $this->assertOrg($catalogItem);

        // Vier-Augen-Modus (MVP-095): statt direkter Übernahme entsteht ein
        // Freigabe-Antrag, den eine zweite Person genehmigen muss.
        if ($this->currentOrganization()->pricingApprovalMode() === 'four_eyes') {
            /** @var \App\Models\User $requester */
            $requester = Auth::user();

            try {
                app(\App\Services\Procurement\PriceApprovalService::class)->request($catalogItem, $requester);
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', __('procurement.approval.flash.requested'));
        }

        try {
            $suggestion = $pricing->applyToArticle($catalogItem);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.margin.flash.applied', ['price' => $suggestion['price']]));
    }

    /**
     * Aktiver OCI-Punchout-Absprung in den Lieferanten-Shop (MVP-096): rendert
     * eine selbst absendende POST-Form an die Shop-Login-URL mit den
     * OCI-Setup-Feldern. Die HOOK_URL für den Warenkorb-Rücksprung ist eine
     * zeitlich begrenzte signierte URL — sie trägt Quelle, Ziel-Lager und den
     * absprungberechtigten Nutzer, da der Cross-Site-POST des Shops keine
     * Session mitbringt.
     */
    public function punchout(Request $request, SupplierCatalogSource $supplierCatalog): \Illuminate\Contracts\View\View|RedirectResponse {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);

        if (! $supplierCatalog->hasPunchout()) {
            return redirect()->route('supplier-catalogs.show', $supplierCatalog)->with('error', __('procurement.oci.flash.no_punchout'));
        }

        $warehouseId = app(SqidEncoder::class)->decode(Warehouse::class, (string) $request->query('warehouse'));
        $warehouse = $warehouseId !== null ? Warehouse::query()->find($warehouseId) : null;
        if (! $warehouse instanceof Warehouse) {
            return redirect()->route('supplier-catalogs.show', $supplierCatalog)->with('error', __('procurement.oci.flash.missing_context'));
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Quelle als ID (kein Sqid am Modell): die HMAC-Signatur der URL
        // verhindert Manipulation/Enumeration.
        $hookUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('oci-carts.return', now()->addHours(2), [
            'source' => $supplierCatalog->id,
            'warehouse' => $warehouse->sqid,
            'user' => $user->sqid,
        ]);

        return view('supplier-catalogs.punchout', [
            'source' => $supplierCatalog,
            'hookUrl' => $hookUrl,
        ]);
    }

    private function assertOrg(SupplierCatalogItem $item): void {
        abort_unless($item->organization_id === $this->currentOrganization()->id, 404);
    }

    /**
     * Liest eine hochgeladene shopinfo.xml und leitet Mapping-Vorschlag,
     * Encoding/Delimiter sowie den Katalog-Download-Hinweis ab (MVP-092). Die
     * URL wird nicht verfolgt — nur angezeigt; Mapping wird zur Vorbefüllung
     * der Importmaske gemerkt.
     */
    public function discoverShopinfo(Request $request, SupplierCatalogSource $supplierCatalog, ShopinfoParser $parser): RedirectResponse {
        $this->canManage();
        $this->assertSourceOrg($supplierCatalog);
        $request->validate(['shopinfo' => ['required', 'file', 'max:4096']]);

        $content = (string) file_get_contents((string) $request->file('shopinfo')?->getRealPath());
        try {
            $discovery = $parser->parse($content);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $changes = [];
        if (! empty($discovery['delimiter'])) {
            $changes['delimiter'] = substr((string) $discovery['delimiter'], 0, 4);
        }
        if (! empty($discovery['charset'])) {
            $changes['encoding'] = substr((string) $discovery['charset'], 0, 32);
        }
        if ($discovery['mapping'] !== []) {
            $changes['mapping'] = $discovery['mapping']; // persistiert für automatischen Abruf
        }
        if ($changes !== []) {
            $supplierCatalog->forceFill($changes)->save();
        }

        return back()
            ->with('success', __('procurement.catalog.shopinfo.flash.discovered', ['count' => count($discovery['mapping'])]))
            ->with('shopinfo_mapping', $discovery['mapping'])
            ->with('shopinfo_url', $discovery['catalog_url']);
    }

    private function assertSourceOrg(SupplierCatalogSource $source): void {
        abort_unless($source->organization_id === $this->currentOrganization()->id, 404);
    }

    /**
     * Baut das CSV-Mapping (Zielfeld => Spaltenname) aus dem Request.
     *
     * @return array<string, string>
     */
    private function mappingFromRequest(Request $request): array {
        /** @var array<string, string> $raw */
        $raw = (array) $request->input('mapping', []);
        $mapping = [];
        foreach (self::MAPPING_FIELDS as $field) {
            $column = trim((string) ($raw[$field] ?? ''));
            if ($column !== '') {
                $mapping[$field] = $column;
            }
        }

        // Freie Zusatzattribute (MVP-541): Code wird geslugt und auf die Länge
        // der Options-Codes (article_option_definitions.code, 40) gekappt.
        /** @var array<int, array{code?: string, column?: string}> $attrRows */
        $attrRows = (array) $request->input('mapping_attr', []);
        foreach ($attrRows as $rowInput) {
            $code = \Illuminate\Support\Str::slug((string) ($rowInput['code'] ?? ''), '_');
            $column = trim((string) ($rowInput['column'] ?? ''));
            if ($code === '' || $column === '') {
                continue;
            }
            $mapping['attr.' . mb_substr($code, 0, 40)] = $column;
        }

        return $mapping;
    }

    private function canView(): void {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryPost->value) ?? false), 403);
    }

    private function canManage(): void {
        Gate::authorize(P::InventoryPost->value);
    }
}
