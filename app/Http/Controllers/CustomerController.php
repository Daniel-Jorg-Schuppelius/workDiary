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

use App\Http\Requests\SaveCustomerRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ExternalReference;
use App\Models\Tag;
use App\Models\TimeEntry;
use App\Models\User;
use App\Plugins\Contracts\PluginCapability;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use App\Services\CustomerCsvImporter;
use App\Services\CustomerStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    private const ALLOWED_SORTS = ['name', 'number', 'company', 'created_at'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $status = $request->string('status')->toString() ?: 'active';
        $search = $request->string('q')->toString();
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'name';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $customers = Customer::query()
            ->search($search)
            ->when($status === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when($status === 'billable_pending', function ($q): void {
                $q->whereNull('archived_at')->withUnexportedBillable();
            })
            ->withCount('projects')
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function show(Customer $customer, PluginManager $plugins, CustomerStatsService $stats): View
    {
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

        $lexoffice = $plugins->withCapability(PluginCapability::TIME_EXPORT)->get(LexofficePlugin::ID);
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

        return view('customers.show', [
            'customer' => $customer,
            'projects' => $projects,
            'defaultProject' => $defaultProject,
            'statsTotal' => $stats->forCustomer($customer),
            'statsMonth' => $stats->forCustomer($customer, $stats->currentMonthRange()),
            'totalMinutes' => $totalMinutes,
            'totalRate' => $totalRate,
            'lexofficePlugin' => $lexoffice,
            'lexofficeContactRef' => $lexofficeContactRef,
            'lexofficeVouchers' => $lexofficeVouchers,
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

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('customers._form_dialog', [
            'customer' => null,
            'isDialog' => true,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse
    {
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

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('customers._form_dialog', [
            'customer' => $customer,
            'isDialog' => true,
            'allTags' => Tag::query()->orderBy('name')->get(),
        ]);
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse
    {
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

    public function destroy(Customer $customer): RedirectResponse
    {
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

    public function archive(Customer $customer): RedirectResponse
    {
        Gate::authorize('archive', $customer);

        $customer->forceFill(['archived_at' => now()])->save();

        return back()->with('success', __('Kunde archiviert.'));
    }

    public function restore(Customer $customer): RedirectResponse
    {
        Gate::authorize('restore', $customer);

        $customer->forceFill(['archived_at' => null])->save();

        return back()->with('success', __('Kunde wiederhergestellt.'));
    }

    /**
     * CSV-Export der aktuell sichtbaren Kunden (Filter & Suche aus Request).
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $status = $request->string('status')->toString() ?: 'active';
        $search = $request->string('q')->toString();

        $query = Customer::query()
            ->search($search)
            ->when($status === 'active', fn ($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn ($q) => $q->whereNotNull('archived_at'))
            ->when($status === 'billable_pending', function ($q): void {
                $q->whereNull('archived_at')->withUnexportedBillable();
            })
            ->orderBy('name');

        $filename = 'kunden-'.now()->format('Y-m-d-His').'.csv';

        return new StreamedResponse(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // UTF-8 BOM für Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
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
            ], ';');

            $query->chunk(500, function ($chunk) use ($out): void {
                /** @var Collection<int, Customer> $chunk */
                foreach ($chunk as $c) {
                    fputcsv($out, [
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
                        $c->currency,
                        $c->hourly_rate,
                        $c->billable ? 'ja' : 'nein',
                        $c->archived_at?->format('Y-m-d') ?? '',
                        $c->created_at?->format('Y-m-d') ?? '',
                    ], ';');
                }
            });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Push aller noch nicht synchronisierten Kunden zu Lexoffice.
     */
    public function bulkPushLexoffice(PluginManager $plugins): RedirectResponse
    {
        Gate::authorize('viewAny', Customer::class);
        /** @var User|null $authUser */
        $authUser = Auth::user();
        if (! $authUser?->canManageBilling()) {
            abort(403);
        }

        /** @var LexofficePlugin|null $lex */
        $lex = $plugins->withCapability(PluginCapability::CONTACT_SYNC)->get(LexofficePlugin::ID);
        if ($lex === null) {
            return back()->with('error', __('Lexoffice-Plugin ist nicht aktiviert.'));
        }

        $alreadySyncedIds = ExternalReference::query()
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', (new Customer)->getMorphClass())
            ->pluck('referenceable_id')
            ->all();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Customer> $candidates */
        $candidates = Customer::query()
            ->whereNull('archived_at')
            ->whereNotIn('id', $alreadySyncedIds)
            ->get();

        $ok = 0;
        $fail = 0;
        foreach ($candidates as $customer) {
            try {
                $lex->pushContact($customer);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                report($e);
            }
        }

        $msg = __('Lexoffice-Sync: :ok übertragen, :fail Fehler.', ['ok' => $ok, 'fail' => $fail]);

        return back()->with($fail > 0 ? 'info' : 'success', $msg);
    }

    /**
     * Zeigt das CSV-Import-Formular.
     */
    public function importForm(): View
    {
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
    public function import(Request $request, CustomerCsvImporter $importer): RedirectResponse
    {
        Gate::authorize('viewAny', Customer::class);
        /** @var User|null $authUser */
        $authUser = Auth::user();
        if (! $authUser?->canManageBilling()) {
            abort(403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel', 'max:10240'],
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
                ->with('error', $message.' Fehler: '.implode(' | ', array_slice($result['errors'], 0, 5)));
        }

        return redirect()->route('customers.index')->with('success', $message);
    }
}
