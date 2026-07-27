<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\{ArchivesModels, ParsesIndexQuery, ResolvesGlobalDateRange};
use App\Http\Requests\SaveSupplierRequest;
use App\Models\{AuditLog, ExternalReference, LexofficeVoucher, Supplier, Tag};
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use App\Services\Stammdaten\{ContactMasterDataPusher, IdentifierIssueDetector};
use App\Support\{CsvExport, Setting};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller {
    use ArchivesModels;
    use ParsesIndexQuery;
    use ResolvesGlobalDateRange;

    private const ALLOWED_SORTS = ['name', 'number', 'company', 'created_at'];

    public function index(Request $request): View {
        Gate::authorize('viewAny', Supplier::class);

        ['status' => $status, 'search' => $search, 'sort' => $sort, 'dir' => $dir]
            = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'name');

        $suppliers = Supplier::query()
            ->search($search)
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->orderBy($sort, $dir)
            ->paginate((int) Setting::get('pagination.customers', 25))
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function show(Supplier $supplier, PluginManager $plugins): View {
        Gate::authorize('view', $supplier);

        $lexoffice = $plugins->withCapability(PluginCapability::TimeExport)->get(LexofficePlugin::ID);
        $lexofficeContactRef = $lexoffice
            ? ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $supplier->getMorphClass())
            ->where('referenceable_id', $supplier->getKey())
            ->first()
            : null;

        // Lexoffice-Belege auf den globalen Header-Zeitraum eingrenzen (analog Kunde).
        $lexofficeVoucherRange = $this->globalDateRange();

        // KPI-Kacheln analog zur Kunden-Detailseite — Beschaffungszahlen nur
        // mit aktivem Lager-Modul (purchase-orders.* sind darauf gegated).
        $procurementStats = null;
        if (app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled('module.lager')) {
            $procurementStats = [
                'articles' => \App\Models\ArticleSupply::query()->where('supplier_id', $supplier->id)->count(),
                'orders' => \App\Models\PurchaseOrder::query()->where('supplier_id', $supplier->id)->count(),
                'open_orders' => \App\Models\PurchaseOrder::query()
                    ->where('supplier_id', $supplier->id)
                    ->whereIn('status', [
                        \App\Enums\Procurement\PurchaseOrderStatus::Draft,
                        \App\Enums\Procurement\PurchaseOrderStatus::Ordered,
                        \App\Enums\Procurement\PurchaseOrderStatus::PartiallyReceived,
                    ])->count(),
            ];
        }

        return view('suppliers.show', [
            'identifierIssues' => app(IdentifierIssueDetector::class)->forModel($supplier),
            'supplier' => $supplier,
            'procurementStats' => $procurementStats,
            'lexofficePlugin' => $lexoffice,
            'lexofficeContactRef' => $lexofficeContactRef,
            'lexofficeVoucherRange' => $lexofficeVoucherRange,
            'lexofficeVoucherCache' => $lexoffice
                ? LexofficeVoucher::query()
                ->where('supplier_id', $supplier->getKey())
                ->where('archived', false)
                ->whereBetween('voucher_date', [
                    $lexofficeVoucherRange['from']->startOfDay(),
                    $lexofficeVoucherRange['to']->endOfDay(),
                ])
                ->orderByDesc('voucher_date')
                ->limit(500)
                ->get()
                : collect(),
            'attachments' => $supplier->attachments()->get(),
            'tags' => $supplier->tags()->get(),
            'auditLogs' => AuditLog::query()
                ->where('auditable_type', $supplier->getMorphClass())
                ->where('auditable_id', $supplier->getKey())
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Supplier::class);

        return view('suppliers._form_dialog', [
            'supplier' => null,
            'isDialog' => true,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse {
        Gate::authorize('create', Supplier::class);

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? [];
        $newTagsRaw = (string) ($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags']);

        $supplier = Supplier::create($data + ['created_by' => Auth::id()]);
        $supplier->syncTagsFromInput($tagIds, \App\Support\TagInput::names($newTagsRaw));

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', __('Lieferant angelegt.'));
    }

    public function edit(Supplier $supplier): View {
        Gate::authorize('update', $supplier);

        return view('suppliers._form_dialog', [
            'supplier' => $supplier,
            'isDialog' => true,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse {
        Gate::authorize('update', $supplier);

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? [];
        $newTagsRaw = (string) ($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags']);

        // fill() vor save(): getDirty() kennt die Änderungen erst danach.
        $supplier->fill($data);
        $changed = array_keys($supplier->getDirty());
        $supplier->save();

        // Korrigierte Stammdaten zurück an Lexoffice — sonst holt der nächste
        // Abgleich den alten Wert wieder.
        $pushed = app(ContactMasterDataPusher::class)->pushIfLinked($supplier, $changed);
        $supplier->syncTagsFromInput($tagIds, \App\Support\TagInput::names($newTagsRaw));

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', $pushed
                ? __('Lieferant aktualisiert und an Lexoffice übertragen.')
                : __('Lieferant aktualisiert.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse {
        Gate::authorize('delete', $supplier);

        if ($supplier->externalReferences()->exists()) {
            return redirect()->route('suppliers.show', $supplier)
                ->with('error', __('Lieferant kann nicht gelöscht werden: Es existieren externe Referenzen (z. B. Lexoffice). Bitte stattdessen archivieren.'));
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', __('Lieferant gelöscht.'));
    }

    public function archive(Supplier $supplier): RedirectResponse {
        return $this->archiveModel($supplier, __('Lieferant archiviert.'));
    }

    public function restore(Supplier $supplier): RedirectResponse {
        return $this->restoreModel($supplier, __('Lieferant wiederhergestellt.'));
    }

    /**
     * CSV-Export der aktuell sichtbaren Lieferanten (Filter & Suche aus Request).
     */
    public function export(Request $request): StreamedResponse {
        Gate::authorize('viewAny', Supplier::class);

        $status = $request->string('status')->toString() ?: 'active';
        $search = $request->string('q')->toString();

        $query = Supplier::query()
            ->search($search)
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->orderBy('name');

        $filename = 'lieferanten-' . now()->format('Y-m-d-His') . '.csv';

        $rows = (static function () use ($query): \Generator {
            /** @var Supplier $s */
            foreach ($query->lazy(500) as $s) {
                yield [
                    $s->number,
                    $s->vendor_number,
                    $s->name,
                    $s->company,
                    $s->vat_id,
                    $s->email,
                    $s->phone ?: $s->mobile,
                    $s->address_street,
                    $s->address_zip,
                    $s->address_city,
                    $s->country,
                    $s->currency->value,
                    $s->active ? 'ja' : 'nein',
                    $s->archived_at?->format('Y-m-d') ?? '',
                    $s->created_at?->format('Y-m-d') ?? '',
                ];
            }
        })();

        return CsvExport::streamFromRows($filename, [
            'Nummer',
            'Lieferantennummer',
            'Name',
            'Firma',
            'USt-IdNr.',
            'E-Mail',
            'Telefon',
            'Straße',
            'PLZ',
            'Ort',
            'Land',
            'Währung',
            'Aktiv',
            'Archiviert',
            'Angelegt',
        ], $rows);
    }
}
