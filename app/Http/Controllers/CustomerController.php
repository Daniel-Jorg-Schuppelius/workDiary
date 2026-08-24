<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Import\ImportEntity;
use App\Http\Controllers\Concerns\{ArchivesModels, ParsesIndexQuery, ResolvesGlobalDateRange};
use App\Http\Requests\SaveCustomerRequest;
use App\Models\{Customer, Organization, Tag, User};
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use App\Services\Customer\CustomerDetailAssembler;
use App\Services\Import\DirectCsvImportService;
use App\Services\Stammdaten\ContactMasterDataPusher;
use App\Support\{CsvExport, Setting};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller {
    use \App\Http\Controllers\Concerns\WritesContactDetails;
    use ArchivesModels;
    use ParsesIndexQuery;
    use ResolvesGlobalDateRange;

    private const ALLOWED_SORTS = ['name', 'number', 'company', 'created_at'];

    public function index(Request $request, PluginManager $plugins): View {
        Gate::authorize('viewAny', Customer::class);

        ['status' => $status, 'search' => $search, 'sort' => $sort, 'dir' => $dir]
            = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'name');

        // Lexoffice „alle pushen" nur bei org-aktivem Plugin anbieten (gleicher Check wie die Aktion).
        $lexofficeEnabled = $plugins->withCapability(PluginCapability::TimeExport)->get(LexofficePlugin::ID) !== null;

        $customers = Customer::query()
            ->search($search)
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->when($status === 'billable_pending', function ($q): void {
                $q->whereNull('archived_at')->withUnexportedBillable();
            })
            ->withCount('projects')
            ->orderBy($sort, $dir)
            ->paginate((int) Setting::get('pagination.customers', 25))
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'lexofficeEnabled' => $lexofficeEnabled,
        ]);
    }

    public function show(Request $request, Customer $customer, CustomerDetailAssembler $assembler): View {
        Gate::authorize('view', $customer);

        /** @var User $viewer */
        $viewer = Auth::user();

        // Timeline-Filter/-Fenster sind Request-Concern und bleiben deshalb hier
        // (MVP-340, Muster wie DiaryController); ebenso der globale Header-Zeitraum.
        $timelineType = (string) $request->query('timeline_type', '');
        $timelineLimit = max(1, min(500, (int) $request->query('timeline_limit', 15)));

        return view('customers.show', $assembler->assemble(
            $customer,
            $viewer,
            $this->globalDateRange(),
            $timelineType,
            $timelineLimit,
        ));
    }

    public function create(): View {
        Gate::authorize('create', Customer::class);

        return view('customers._form_dialog', [
            'customer' => null,
            'isDialog' => true,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse {
        Gate::authorize('create', Customer::class);

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? [];
        $newTagsRaw = (string) ($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags']);
        $contactDetails = $this->pullContactDetails($data);

        $customer = Customer::create($data + ['created_by' => Auth::id()]);
        $this->writeContactDetails($customer, $contactDetails);
        $customer->syncTagsFromInput($tagIds, \App\Support\TagInput::names($newTagsRaw));

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Kunde angelegt.'));
    }

    public function edit(Customer $customer): View {
        Gate::authorize('update', $customer);

        return view('customers._form_dialog', [
            'customer' => $customer,
            'isDialog' => true,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse {
        Gate::authorize('update', $customer);

        $data = $request->validated();
        $tagIds = $data['tag_ids'] ?? [];
        $newTagsRaw = (string) ($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags']);
        $contactDetails = $this->pullContactDetails($data);

        // fill() vor save(): getDirty() kennt die Änderungen erst danach.
        $customer->fill($data);
        $changed = array_keys($customer->getDirty());
        $customer->save();
        $changed = array_merge($changed, $this->writeContactDetails($customer, $contactDetails));
        $customer->syncTagsFromInput($tagIds, \App\Support\TagInput::names($newTagsRaw));

        // Korrigierte Stammdaten zurück an Lexoffice — sonst holt der nächste
        // Abgleich den alten Wert wieder.
        $pushed = app(ContactMasterDataPusher::class)->pushIfLinked($customer, $changed);

        // Abrechenbar-Schalter auf offene Zeiten durchziehen — billable ist am
        // Eintrag ein Snapshot und bliebe sonst auf dem alten Wert stehen.
        $syncedBillable = in_array('billable', $changed, true)
            ? app(\App\Services\Billing\TimeEntryBillableSyncService::class)->syncCustomer($customer)
            : 0;

        $message = $pushed
            ? __('Kunde aktualisiert und an Lexoffice übertragen.')
            : __('Kunde aktualisiert.');
        if ($syncedBillable > 0) {
            $message .= ' ' . trans_choice(':count offener Zeiteintrag an die neue Abrechenbarkeit angepasst.|:count offene Zeiteinträge an die neue Abrechenbarkeit angepasst.', $syncedBillable, ['count' => $syncedBillable]);
        }

        return redirect()->route('customers.show', $customer)
            ->with('success', $message);
    }

    public function destroy(Customer $customer): RedirectResponse {
        Gate::authorize('delete', $customer);

        if ($customer->hasNonDefaultProjects()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', __('Kunde kann nicht gelöscht werden: Es existieren noch Projekte. Bitte zuerst archivieren oder Projekte entfernen.'));
        }

        if ($customer->externalReferences()->exists()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', __('Kunde kann nicht gelöscht werden: Es existieren externe Referenzen (z. B. Lexoffice). Bitte stattdessen archivieren.'));
        }

        // Vollaudit 2026-07 (M9): KI-Gedächtnis auditiert löschen (Einzel-Audit
        // je Eintrag + Provider-Glossar-Hook) statt stiller FK-Kaskade.
        app(\App\Services\Ai\AiMemoryService::class)->deleteForCustomer(
            $customer->organization()->firstOrFail(),
            (int) $customer->id,
        );

        // Standardprojekt(e) zusammen mit dem Kunden entfernen.
        $customer->projects()->where('is_default', true)->delete();
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', __('Kunde gelöscht.'));
    }

    public function archive(Customer $customer): RedirectResponse {
        return $this->archiveModel($customer, __('Kunde archiviert.'));
    }

    public function restore(Customer $customer): RedirectResponse {
        return $this->restoreModel($customer, __('Kunde wiederhergestellt.'));
    }

    /**
     * CSV-Export der aktuell sichtbaren Kunden (Filter & Suche aus Request).
     */
    public function export(Request $request): StreamedResponse {
        Gate::authorize('viewAny', Customer::class);

        $status = $request->string('status')->toString() ?: 'active';
        $search = $request->string('q')->toString();

        $query = Customer::query()
            ->search($search)
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->when($status === 'billable_pending', function ($q): void {
                $q->whereNull('archived_at')->withUnexportedBillable();
            })
            ->orderBy('name');

        $filename = 'kunden-' . now()->format('Y-m-d-His') . '.csv';

        $rows = (static function () use ($query): \Generator {
            /** @var Customer $c */
            foreach ($query->lazy(500) as $c) {
                yield [
                    $c->number,
                    $c->name,
                    $c->company,
                    $c->vat_id,
                    $c->email,
                    $c->phone ?: $c->mobile,
                    $c->address_street,
                    $c->address_zip,
                    $c->address_city,
                    $c->country,
                    $c->currency->value,
                    $c->hourly_rate,
                    $c->billable ? 'ja' : 'nein',
                    $c->archived_at?->format('Y-m-d') ?? '',
                    $c->created_at?->format('Y-m-d') ?? '',
                ];
            }
        })();

        return CsvExport::streamFromRows($filename, [
            'Nummer',
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
            'Stundensatz',
            'Abrechenbar',
            'Archiviert',
            'Angelegt',
        ], $rows);
    }

    /**
     * Zeigt das CSV-Import-Formular.
     */
    public function importForm(): View {
        Gate::authorize('viewAny', Customer::class);
        /** @var User|null $authUser */
        $authUser = Auth::user();
        if (! $authUser?->canManageBilling()) {
            abort(403);
        }

        return view('customers.import');
    }

    /**
     * Verarbeitet einen CSV-Upload und legt/aktualisiert Kunden — synchron
     * über die EntitySpec-Pipeline (CustomerSpec, Direkt-Upsert ohne Inbox).
     */
    public function import(Request $request, DirectCsvImportService $importer): RedirectResponse {
        Gate::authorize('viewAny', Customer::class);
        /** @var User|null $authUser */
        $authUser = Auth::user();
        if (! $authUser?->canManageBilling()) {
            abort(403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:' . (int) Setting::get('uploads.csv_import_kb', 10240)],
        ]);

        $file = $request->file('file');
        if ($file === null) {
            return back()->with('error', __('Keine Datei hochgeladen.'));
        }

        $organization = app()->bound('currentOrganization') && app('currentOrganization') instanceof Organization
            ? app('currentOrganization')
            : $authUser->organization;
        abort_unless($organization instanceof Organization, 403);

        $result = $importer->import($file, ImportEntity::Customers, $organization);

        $message = __('CSV-Import: :c angelegt, :u aktualisiert, :s übersprungen.', [
            'c' => $result['created'],
            'u' => $result['updated'],
            's' => $result['skipped'],
        ]);

        if ($result['errors'] !== []) {
            return redirect()->route('customers.index')
                ->with('error', $message . ' Fehler: ' . implode(' | ', array_slice($result['errors'], 0, 5)));
        }

        return redirect()->route('customers.index')->with('success', $message);
    }
}
