<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Supplier;

use App\Enums\Numbering\NumberScope;
use App\Exceptions\PartyDeletionBlockedException;
use App\Http\Controllers\Concerns\{ArchivesModels, ParsesIndexQuery, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\SaveSupplierRequest;
use App\Models\Classification\Tag;
use App\Models\Supplier\Supplier;
use App\Services\Numbering\NumberAuthority;
use App\Services\Stammdaten\{ContactMasterDataPusher, PartyDeletionGuard};
use App\Services\Supplier\SupplierDetailAssembler;
use App\Support\{CarbonFmt, CsvExport, Setting};
use App\Support\OrganizationContext;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller {
    use \App\Http\Controllers\Concerns\IssuesContactNumbers;
    use \App\Http\Controllers\Concerns\SavesCustomFields;
    use \App\Http\Controllers\Concerns\WritesContactDetails;
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

    public function show(Request $request, Supplier $supplier): View {
        Gate::authorize('view', $supplier);

        // Belege auf den globalen Header-Zeitraum eingrenzen (analog
        // Kunde); explizite from/to-Parameter (Drilldown aus der
        // Lieferantenanalyse) haben Vorrang.
        $voucherRange = $this->globalDateRange();
        if ($request->filled('from') && $request->filled('to')) {
            [$rangeFrom, $rangeTo] = $this->resolveRange($request);
            $voucherRange = array_merge($voucherRange, [
                'from' => $rangeFrom,
                'to' => $rangeTo,
                'label' => CarbonFmt::fdate($rangeFrom) . ' – ' . CarbonFmt::fdate($rangeTo),
            ]);
        }

        $granularity = $this->bucketGranularity($voucherRange['from'], $voucherRange['to']);

        return view('suppliers.show', app(SupplierDetailAssembler::class)->assemble($supplier, $this->authUser(), $voucherRange, $this->globalUnit()) + [
            'periodPhrase' => $this->periodPhrase($granularity),
            'periodAxis' => $this->periodAxisLabel($granularity),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', Supplier::class);

        return view('suppliers._form_dialog', [
            'supplier' => null,
            'isDialog' => true,
            'numberLockedBy' => app(NumberAuthority::class)->numberLockedBy(NumberScope::Supplier, OrganizationContext::currentId() ?? Auth::user()?->organization_id),
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse {
        Gate::authorize('create', Supplier::class);

        $data = $request->validated();
        $custom = $this->validatedCustomFields($request, Supplier::class);
        $tagIds = $data['tag_ids'] ?? [];
        $newTagsRaw = (string) ($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags'], $data['custom']);
        $contactDetails = $this->pullContactDetails($data);

        $supplier = Supplier::create($data + ['created_by' => Auth::id()]);
        $this->writeContactDetails($supplier, $contactDetails);
        $supplier->syncTagsFromInput($tagIds, \App\Support\TagInput::names($newTagsRaw));
        $supplier->syncCustomFields($custom);

        return redirect()->route('suppliers.show', $supplier)
            ->with($this->issueContactNumber($supplier) + ['success' => __('Lieferant angelegt.')]);
    }

    public function edit(Supplier $supplier): View {
        Gate::authorize('update', $supplier);

        return view('suppliers._form_dialog', [
            'supplier' => $supplier,
            'isDialog' => true,
            'numberLockedBy' => app(NumberAuthority::class)->numberLockedBy(NumberScope::Supplier, $supplier->organization_id, (string) $supplier->number_source),
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse {
        Gate::authorize('update', $supplier);

        $data = $request->validated();
        $custom = $this->validatedCustomFields($request, Supplier::class);
        $tagIds = $data['tag_ids'] ?? [];
        $newTagsRaw = (string) ($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags'], $data['custom']);
        $contactDetails = $this->pullContactDetails($data);

        // fill() vor save(): getDirty() kennt die Änderungen erst danach.
        $supplier->fill($data);
        $changed = array_keys($supplier->getDirty());
        $supplier->save();
        $changed = array_merge($changed, $this->writeContactDetails($supplier, $contactDetails));

        // Korrigierte Stammdaten zurück ans Buchhaltungsprogramm — sonst holt der nächste
        // Abgleich den alten Wert wieder.
        $pushed = app(ContactMasterDataPusher::class)->pushIfLinked($supplier, $changed);
        $supplier->syncTagsFromInput($tagIds, \App\Support\TagInput::names($newTagsRaw));
        $supplier->syncCustomFields($custom);

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', $pushed !== []
                ? __('Lieferant aktualisiert und an :system übertragen.', ['system' => implode(', ', $pushed)])
                : __('Lieferant aktualisiert.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse {
        Gate::authorize('delete', $supplier);

        if ($supplier->externalReferences()->exists()) {
            return redirect()->route('suppliers.show', $supplier)
                ->with('error', __('Lieferant kann nicht gelöscht werden: Es existieren externe Referenzen (z. B. Lexoffice). Bitte stattdessen archivieren.'));
        }
        // Am Sammellieferanten hängen die Belege vieler Einmallieferanten (MVP-1109).
        if ($supplier->is_collective && \App\Models\Invoicing\IncomingEInvoice::query()->where('supplier_id', $supplier->id)->exists()) {
            return redirect()->route('suppliers.show', $supplier)
                ->with('error', __('Der Sammelkontakt hat Belege und lässt sich nicht löschen.'));
        }

        try {
            app(PartyDeletionGuard::class)->assertDeletable($supplier);
        } catch (PartyDeletionBlockedException $e) {
            return redirect()->route('suppliers.show', $supplier)->with('error', $e->getMessage());
        }

        $supplier->delete();

        return redirect()->toList('suppliers.index')
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
        Gate::authorize('export', Supplier::class);

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
