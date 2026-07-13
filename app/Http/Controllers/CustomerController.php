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

use App\Http\Controllers\Concerns\{ArchivesModels, ParsesIndexQuery, ResolvesGlobalDateRange};
use App\Http\Requests\SaveCustomerRequest;
use App\Models\{AuditLog, Customer, ExternalReference, Invoice, LexofficeVoucher, Tag, TimeEntry, User};
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use App\Services\{CustomerCsvImporter, CustomerStatsService};
use App\Support\Setting;
use CommonToolkit\Helper\Data\CSV\StringHelper;
// HINWEIS: Lexoffice-Push-Logik ist in das Plugin verlagert
// (App\Plugins\Lexoffice\Http\Controllers\LexofficeCustomerController).
// Die Imports oben werden nur noch für die Show-View (Anzeige der bisherigen
// Referenzen) gebraucht.
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller {
    use ArchivesModels;
    use ParsesIndexQuery;
    use ResolvesGlobalDateRange;

    private const ALLOWED_SORTS = ['name', 'number', 'company', 'created_at'];

    public function index(Request $request, PluginManager $plugins): View {
        Gate::authorize('viewAny', Customer::class);

        ['status' => $status, 'search' => $search, 'sort' => $sort, 'dir' => $dir]
            = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'name');

        // Lexoffice „alle pushen" nur anbieten, wenn das Plugin in der aktiven
        // Organisation tatsächlich aktiv ist (gleicher Check wie die Aktion) —
        // sonst erscheint der Button, läuft aber in „nicht aktiviert".
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

    public function show(Customer $customer, PluginManager $plugins, CustomerStatsService $stats): View {
        Gate::authorize('view', $customer);

        $defaultProject = $customer->defaultProjectOrCreate();

        $projects = $customer->projects()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $projectIds = $projects->pluck('id')->all();

        $totalMinutes = (int) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->sum('minutes');

        $totalRate = (float) TimeEntry::query()
            ->whereIn('project_id', $projectIds)
            ->sum('rate');

        $lexoffice = $plugins->withCapability(PluginCapability::TimeExport)->get(LexofficePlugin::ID);
        $lexofficeContactRef = $lexoffice
            ? ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->first()
            : null;
        $lexofficeVouchers = $lexoffice
            ? ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_VOUCHER)
            ->where('referenceable_type', $customer->getMorphClass())
            ->where('referenceable_id', $customer->getKey())
            ->orderByDesc('synced_at')
            ->limit(10)
            ->get()
            : collect();

        // Lexoffice-Belege (Rechnungen/Aufträge/Angebote …) auf den globalen
        // Header-Zeitraum eingrenzen — wie die übrigen zeitraumbezogenen Ansichten.
        $lexofficeVoucherRange = $this->globalDateRange();

        // Lokale Rechnungen (App\Models\Invoice) desselben Kunden im Header-Zeitraum.
        // Zusammen mit den Lexoffice-Belegen ergibt das die Rechnungssicht.
        $localInvoices = Gate::allows('viewAny', Invoice::class)
            ? Invoice::query()
            ->where('customer_id', $customer->getKey())
            ->whereBetween('issued_on', [
                $lexofficeVoucherRange['from']->startOfDay(),
                $lexofficeVoucherRange['to']->endOfDay(),
            ])
            ->orderByDesc('issued_on')
            ->limit(500)
            ->get()
            : collect();

        // Vollwertige Kunden-Timeline (MVP-340): serverseitiger Typ-Filter +
        // Nachlade-Fenster — Muster der Auftrags-Detailseite (DiaryController).
        /** @var User $viewer */
        $viewer = Auth::user();
        $timelineType = (string) request()->query('timeline_type', '');
        $timelineLimit = max(1, min(500, (int) request()->query('timeline_limit', 15)));
        $timeline = app(\App\Services\Timeline\DiaryEntryTimelineService::class)->forCustomer(
            $customer,
            $viewer,
            $timelineType !== '' ? [$timelineType] : null,
            $timelineLimit,
        );

        return view('customers.show', [
            'customer' => $customer,
            'timelineItems' => $timeline['items'],
            'timelineHasMore' => $timeline['hasMore'],
            'timelineType' => $timelineType,
            'timelineLimit' => $timelineLimit,
            'projects' => $projects,
            'defaultProject' => $defaultProject,
            'statsTotal' => $stats->forCustomer($customer),
            'statsMonth' => $stats->forCustomer($customer, $stats->currentMonthRange()),
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
            'lexofficePlugin' => $lexoffice,
            'lexofficeContactRef' => $lexofficeContactRef,
            'lexofficeVouchers' => $lexofficeVouchers,
            'lexofficeVoucherRange' => $lexofficeVoucherRange,
            'localInvoices' => $localInvoices,
            'lexofficeVoucherCache' => $lexoffice
                ? LexofficeVoucher::query()
                ->where('customer_id', $customer->getKey())
                ->where('archived', false)
                ->whereBetween('voucher_date', [
                    $lexofficeVoucherRange['from']->startOfDay(),
                    $lexofficeVoucherRange['to']->endOfDay(),
                ])
                ->orderByDesc('voucher_date')
                ->limit(500)
                ->get()
                : collect(),
            'attachments' => $customer->attachments()->get(),
            'tags' => $customer->tags()->get(),
            'auditLogs' => AuditLog::query()
                ->where('auditable_type', $customer->getMorphClass())
                ->where('auditable_id', $customer->getKey())
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
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

        $customer = Customer::create($data + ['created_by' => Auth::id()]);
        $customer->syncTagsFromInput($tagIds, array_filter(array_map('trim', explode(',', $newTagsRaw))));

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

        $customer->update($data);
        $customer->syncTagsFromInput($tagIds, array_filter(array_map('trim', explode(',', $newTagsRaw))));

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Kunde aktualisiert.'));
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

        return new StreamedResponse(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // UTF-8 BOM für Excel
            fwrite($out, \CommonToolkit\Helper\Data\StringHelper::BOM_UTF8);
            fwrite($out, StringHelper::encodeLine([
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
            ], ';') . "\r\n");

            $query->chunk(500, function ($chunk) use ($out): void {
                /** @var Collection<int, Customer> $chunk */
                foreach ($chunk as $c) {
                    fwrite($out, StringHelper::encodeLine([
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
                    ], ';') . "\r\n");
                }
            });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
     * Verarbeitet einen CSV-Upload und legt/aktualisiert Kunden.
     */
    public function import(Request $request, CustomerCsvImporter $importer): RedirectResponse {
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

        $orgId = app()->bound('currentOrganization')
            ? (int) app('currentOrganization')->id
            : (int) (Auth::user()->organization_id ?? 0);

        $result = $importer->import($file, $orgId ?: null);

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
