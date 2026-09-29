<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseStockController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reselling;

use App\Enums\Reselling\LicenseUnitStatus;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Reselling\{ResaleLicenseAssignment, ResaleLicenseBatch, ResaleLicenseProduct, ResaleLicenseUnit};
use App\Models\Supplier\Supplier;
use App\Services\Platform\Catalog\ArticleCatalog;
use App\Services\Reselling\License\{LicenseStockException, LicenseStockService};
use App\Services\Reselling\Purchase\PurchaseDocuments;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\{Rule, ValidationException};

/**
 * Lizenzbestand aus Einkaufspaketen (Feature 152, MVP-1024): Übersicht,
 * Produkte, Pakete und Schlüsselimport. Verkauf und Korrekturen liegen im
 * {@see LicenseSaleController}; geschrieben wird nur über den
 * {@see LicenseStockService}.
 */
class LicenseStockController extends Controller {
    use ResolvesCurrentOrganization;

    public const MAX_IMPORT_KB = 2048;

    public function __construct(
        private readonly LicenseStockService $stock,
        private readonly ArticleCatalog $catalog,
    ) {}

    public function index(Request $request): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $filters = [
            'q' => mb_substr(trim($request->string('q')->toString()), 0, 100),
            'product' => $request->string('product')->toString(),
            'status' => LicenseUnitStatus::tryFrom($request->string('status')->toString())->value ?? '',
            'customer' => $request->string('customer')->toString(),
            'reorder' => $request->boolean('reorder'),
        ];
        $productId = Sqid::decodeOrNumeric(ResaleLicenseProduct::class, $filters['product']);
        $customerId = Sqid::decodeOrNumeric(Customer::class, $filters['customer']);
        $q = $filters['q'];

        $products = ResaleLicenseProduct::query()
            ->with(['batches' => static fn ($b) => $b->orderBy('purchased_on')->orderBy('id')])
            ->when($productId !== null, static fn ($p) => $p->whereKey($productId))
            ->when($q !== '', static fn ($p) => $p->where(static fn ($w) => $w
                ->whereLikeEscaped('name', $q)
                ->orWhereLikeEscaped('manufacturer', $q)
                ->orWhereHas('batches', static fn ($b) => $b->whereLikeEscaped('reference', $q))))
            ->orderBy('name')
            ->get();
        $stock = $this->stock->productStock($organization, $products);
        if ($filters['reorder']) {
            $products = $products->filter(static fn (ResaleLicenseProduct $p): bool => $stock[$p->id]['reorder'])->values();
        }
        $batchCounts = $this->stock->batchCounts($organization, $products->flatMap->batches->pluck('id')->all());

        $status = LicenseUnitStatus::tryFrom($filters['status']);
        $units = ResaleLicenseUnit::query()
            ->withStockState()
            ->with('batch.product')
            ->when($productId !== null, static fn ($u) => $u->whereHas('batch', static fn ($b) => $b->where('product_id', $productId)))
            ->when($status, static fn ($u, LicenseUnitStatus $s) => $u->withStatus($s))
            ->when($customerId !== null, static fn ($u) => $u->whereHas('activeAssignment', static fn ($a) => $a->where('customer_id', $customerId)))
            ->when($q !== '', static fn ($u) => $u->where(static fn ($w) => $w
                ->whereHas('batch', static fn ($b) => $b->whereLikeEscaped('reference', $q)
                    ->orWhereHas('product', static fn ($p) => $p->whereLikeEscaped('name', $q)))
                ->orWhereHas('activeAssignment', static fn ($a) => $a->whereLikeEscaped('invoice_reference', $q)
                    ->orWhereHas('customer', static fn ($c) => $c->whereLikeEscaped('name', $q)))))
            ->orderBy('batch_id')
            ->orderBy('position')
            ->paginate(25)
            ->withQueryString();

        return view('finance.resale.licenses.index', [
            'products' => $products,
            'stock' => $stock,
            'batchCounts' => $batchCounts,
            'units' => $units,
            'filters' => $filters,
            'statuses' => LicenseUnitStatus::cases(),
            'productOptions' => ResaleLicenseProduct::query()->orderBy('name')->get(['id', 'name']),
            'customerOptions' => Customer::query()
                ->whereIn('id', ResaleLicenseAssignment::query()->whereNull('ended_at')->select('customer_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'summary' => [
                'available' => array_sum(array_column($stock, 'available')),
                'sold' => array_sum(array_column($stock, 'sold')),
                'incomplete' => array_sum(array_column($stock, 'incomplete')),
                'reorder' => count(array_filter($stock, static fn (array $s): bool => $s['reorder'])),
            ],
        ]);
    }

    public function productCreate(): View {
        return view('finance.resale.licenses._product_dialog', ['product' => null, 'articleFormKey' => null] + $this->productOptions());
    }

    public function productEdit(ResaleLicenseProduct $product): View {
        return view('finance.resale.licenses._product_dialog', [
            'product' => $product,
            'articleFormKey' => $this->catalog->find((int) $product->organization_id, $product->article_ref)?->formKey,
        ] + $this->productOptions());
    }

    public function productStore(Request $request): RedirectResponse {
        return $this->saveProduct($request, null);
    }

    public function productUpdate(Request $request, ResaleLicenseProduct $product): RedirectResponse {
        return $this->saveProduct($request, $product);
    }

    public function batchCreate(Request $request, PurchaseDocuments $documents): View {
        $organization = $this->currentOrganizationOrAbort(404);

        return view('finance.resale.licenses._batch_dialog', [
            'products' => ResaleLicenseProduct::query()->orderBy('name')->get(['id', 'name']),
            'productSqid' => $request->string('product')->toString(),
            'suppliers' => Supplier::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'documents' => $documents->search($organization, null, CarbonImmutable::now()->subYear(), 50),
        ]);
    }

    public function batchStore(Request $request, PurchaseDocuments $documents): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $data = $request->validate([
            'product_id' => ['required', 'string', 'max:32'],
            'reference' => ['required', 'string', 'max:80', Rule::unique('resale_license_batches', 'reference')->where('organization_id', $organization->id)],
            'purchased_on' => ['required', 'date_format:Y-m-d'],
            'quantity' => ['required', 'integer', 'min:1', 'max:' . LicenseStockService::MAX_BATCH_QUANTITY],
            'supplier_id' => ['nullable', 'string', 'max:32'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'document' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $product = ResaleLicenseProduct::query()->find(Sqid::decodeOrNumeric(ResaleLicenseProduct::class, $data['product_id']) ?? 0);
        if ($product === null) {
            throw ValidationException::withMessages(['product_id' => __('resale.license.error.product_missing')]);
        }
        $supplierId = null;
        if (($data['supplier_id'] ?? '') !== '') {
            $supplierId = Supplier::query()->whereKey(Sqid::decodeOrNumeric(Supplier::class, $data['supplier_id']) ?? 0)->value('id')
                ?? throw ValidationException::withMessages(['supplier_id' => __('resale.license.error.supplier_missing')]);
        }
        $document = null;
        if (($data['document'] ?? '') !== '') {
            $document = $documents->byKey($organization, $data['document'])
                ?? throw ValidationException::withMessages(['document' => __('resale.license.error.document_missing')]);
        }

        try {
            $batch = $this->stock->createBatch($product, [
                'reference' => $data['reference'],
                'purchased_on' => $data['purchased_on'],
                'quantity' => (int) $data['quantity'],
                'supplier_id' => $supplierId,
                'supplier_name' => $data['supplier_name'] ?? null,
                'note' => $data['note'] ?? null,
            ], $document, $this->actor());
        } catch (LicenseStockException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return redirect()->route('finance.resale.licenses.batches.show', $batch)
            ->with('success', __('resale.license.flash.batch_created', ['count' => $batch->quantity]));
    }

    public function batchShow(ResaleLicenseBatch $batch): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $batch->load(['product', 'supplier']);

        return view('finance.resale.licenses.batch', [
            'batch' => $batch,
            'counts' => $this->stock->batchCounts($organization, [$batch->id])->get($batch->id, ['purchased' => 0, 'available' => 0, 'sold' => 0, 'incomplete' => 0, 'blocked' => 0]),
            'units' => $batch->units()->withStockState()->orderBy('position')->paginate(50),
            'history' => ResaleLicenseAssignment::query()
                ->whereIn('unit_id', $batch->units()->getQuery()->select('id'))
                ->with(['unit', 'customer', 'foreignCustomer'])
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
        ]);
    }

    public function batchDestroy(ResaleLicenseBatch $batch): RedirectResponse {
        try {
            $this->stock->deleteBatch($batch);
        } catch (LicenseStockException $e) {
            return redirect()->route('finance.resale.licenses.batches.show', $batch)->with('error', $e->getMessage());
        }

        return redirect()->toList('finance.resale.licenses.index')->with('success', __('resale.license.flash.batch_deleted'));
    }

    public function template(ResaleLicenseBatch $batch): Response {
        return response($this->stock->importTemplate($batch), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="lizenzen-' . $batch->sqid . '.csv"',
        ]);
    }

    public function importCreate(ResaleLicenseBatch $batch): View {
        return view('finance.resale.licenses._import_dialog', ['batch' => $batch]);
    }

    /** Vorschau als eigene Seite (Importmuster): Zeilen, Fehler und die Übernahme erst nach Bestätigung. */
    public function importPreview(Request $request, ResaleLicenseBatch $batch): View {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:' . self::MAX_IMPORT_KB],
        ]);

        return view('finance.resale.licenses.import_preview', [
            'batch' => $batch->load('product'),
            'preview' => $this->stock->previewImport($batch, (string) $request->file('file')?->get(), $this->actor()),
        ]);
    }

    public function importStore(Request $request, ResaleLicenseBatch $batch): RedirectResponse {
        $data = $request->validate(['token' => ['required', 'string', 'size:32']]);

        try {
            $count = $this->stock->confirmImport($batch, $data['token'], $this->actor());
        } catch (LicenseStockException $e) {
            return redirect()->route('finance.resale.licenses.batches.show', $batch)->with('error', $e->getMessage());
        }

        return redirect()->route('finance.resale.licenses.batches.show', $batch)
            ->with('success', trans_choice('resale.license.flash.imported', $count, ['count' => $count]));
    }

    private function saveProduct(Request $request, ?ResaleLicenseProduct $product): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', Rule::unique('resale_license_products', 'name')->where('organization_id', $organization->id)->ignore($product?->id)],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'key_labels' => ['required', 'array', 'max:' . LicenseStockService::MAX_KEY_ROLES],
            'key_labels.*' => ['nullable', 'string', 'max:60'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'article' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $article = null;
        if (($data['article'] ?? '') !== '') {
            $article = $this->catalog->fromFormKey((int) $organization->id, $data['article'])
                ?? throw ValidationException::withMessages(['article' => __('article.catalog.unknown')]);
        }

        try {
            $product = $this->stock->saveProduct($organization, $product, [
                'name' => $data['name'],
                'manufacturer' => $data['manufacturer'] ?? null,
                'key_labels' => array_values($data['key_labels']),
                'reorder_level' => isset($data['reorder_level']) ? (int) $data['reorder_level'] : null,
                'article_ref' => $article?->key,
                'note' => $data['note'] ?? null,
            ], $this->actor());
        } catch (LicenseStockException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return redirect()->toList('finance.resale.licenses.index')
            ->with('success', __('resale.license.flash.product_saved', ['name' => $product->name]));
    }

    /** @return array{catalogArticles: list<\App\Services\Platform\Catalog\CatalogArticle>} */
    private function productOptions(): array {
        return ['catalogArticles' => $this->catalog->active((int) $this->currentOrganizationOrAbort(404)->id)];
    }

    private function actor(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
