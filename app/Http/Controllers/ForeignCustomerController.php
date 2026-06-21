<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignCustomerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\{ArchivesModels, ParsesIndexQuery};
use App\Http\Requests\SaveForeignCustomerRequest;
use App\Models\{AuditLog, Customer, ForeignCustomer, Project};
use App\Support\{Setting, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;

/**
 * CRUD für Fremdkunden (Endkunden) — die Kundschaft einer Firma (Customer).
 * Analog {@see CustomerController}, leichtgewichtig. Zusätzlich `promote`:
 * wandelt einen Fremdkunden in einen vollwertigen Kunden um und hängt dessen
 * Projekte um (für den Fall „Endkunde kommt direkt zu mir").
 */
class ForeignCustomerController extends Controller {
    use ArchivesModels;
    use ParsesIndexQuery;

    private const ALLOWED_SORTS = ['name', 'company', 'created_at'];

    public function index(Request $request): View {
        Gate::authorize('viewAny', ForeignCustomer::class);

        ['status' => $status, 'search' => $search, 'sort' => $sort, 'dir' => $dir]
            = $this->parseIndexQuery($request, self::ALLOWED_SORTS, 'name');

        $customerId = Sqid::decode(Customer::class, (string) $request->string('customer')->toString());

        $foreignCustomers = ForeignCustomer::query()
            ->with('customer:id,name,company')
            ->search($search)
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when($status === 'active', fn($q) => $q->whereNull('archived_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('archived_at'))
            ->withCount('projects')
            ->orderBy($sort, $dir)
            ->paginate((int) Setting::get('pagination.customers', 25))
            ->withQueryString();

        return view('foreign-customers.index', [
            'foreignCustomers' => $foreignCustomers,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'customerFilter' => $customerId !== null ? Customer::query()->find($customerId) : null,
        ]);
    }

    public function show(ForeignCustomer $foreignCustomer): View {
        Gate::authorize('view', $foreignCustomer);

        $projects = $foreignCustomer->projects()->orderBy('name')->get();

        return view('foreign-customers.show', [
            'foreignCustomer' => $foreignCustomer,
            'projects' => $projects,
            'auditLogs' => AuditLog::query()
                ->where('auditable_type', $foreignCustomer->getMorphClass())
                ->where('auditable_id', $foreignCustomer->getKey())
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', ForeignCustomer::class);

        $customerId = Sqid::decode(Customer::class, (string) $request->string('customer')->toString());

        return view('foreign-customers._form_dialog', [
            'foreignCustomer' => null,
            'isDialog' => true,
            'customers' => Customer::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'company']),
            'presetCustomerId' => $customerId,
        ]);
    }

    public function store(SaveForeignCustomerRequest $request): RedirectResponse {
        Gate::authorize('create', ForeignCustomer::class);

        $data = $request->validated();
        $this->assertCustomerInOrganization((int) $data['customer_id']);

        $foreignCustomer = ForeignCustomer::create($data + ['created_by' => Auth::id()]);

        return redirect()->route('foreign-customers.show', $foreignCustomer)
            ->with('success', __('Fremdkunde angelegt.'));
    }

    public function edit(ForeignCustomer $foreignCustomer): View {
        Gate::authorize('update', $foreignCustomer);

        return view('foreign-customers._form_dialog', [
            'foreignCustomer' => $foreignCustomer,
            'isDialog' => true,
            'customers' => Customer::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'company']),
            'presetCustomerId' => $foreignCustomer->customer_id,
        ]);
    }

    public function update(SaveForeignCustomerRequest $request, ForeignCustomer $foreignCustomer): RedirectResponse {
        Gate::authorize('update', $foreignCustomer);

        $data = $request->validated();
        $this->assertCustomerInOrganization((int) $data['customer_id']);

        $foreignCustomer->update($data);

        return redirect()->route('foreign-customers.show', $foreignCustomer)
            ->with('success', __('Fremdkunde aktualisiert.'));
    }

    public function destroy(ForeignCustomer $foreignCustomer): RedirectResponse {
        Gate::authorize('delete', $foreignCustomer);

        if ($foreignCustomer->projects()->exists()) {
            return redirect()->route('foreign-customers.show', $foreignCustomer)
                ->with('error', __('Fremdkunde kann nicht gelöscht werden: Es existieren noch Projekte. Bitte zuerst archivieren.'));
        }
        if ($foreignCustomer->externalReferences()->exists()) {
            return redirect()->route('foreign-customers.show', $foreignCustomer)
                ->with('error', __('Fremdkunde kann nicht gelöscht werden: Es existieren externe Referenzen. Bitte stattdessen archivieren.'));
        }

        $customer = $foreignCustomer->customer;
        $foreignCustomer->delete();

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Fremdkunde gelöscht.'));
    }

    public function archive(ForeignCustomer $foreignCustomer): RedirectResponse {
        return $this->archiveModel($foreignCustomer, __('Fremdkunde archiviert.'));
    }

    public function restore(ForeignCustomer $foreignCustomer): RedirectResponse {
        return $this->restoreModel($foreignCustomer, __('Fremdkunde wiederhergestellt.'));
    }

    /**
     * Befördert einen Fremdkunden zu einem vollwertigen Kunden: legt einen
     * Customer aus den Kontaktdaten an, hängt alle Projekte des Fremdkunden um
     * (customer_id = neuer Kunde, foreign_customer_id = null — die Project-Hooks
     * ziehen DiaryEntries/Draft-Rechnungen nach) und archiviert den Fremdkunden.
     */
    public function promote(ForeignCustomer $foreignCustomer): RedirectResponse {
        Gate::authorize('promote', $foreignCustomer);

        $customer = DB::transaction(function () use ($foreignCustomer): Customer {
            $customer = Customer::create([
                'organization_id' => $foreignCustomer->organization_id,
                'name' => $foreignCustomer->name,
                'company' => $foreignCustomer->company,
                'contact_name' => $foreignCustomer->contact_name,
                'email' => $foreignCustomer->email,
                'phone' => $foreignCustomer->phone,
                'mobile' => $foreignCustomer->mobile,
                'homepage' => $foreignCustomer->homepage,
                'address' => $foreignCustomer->address,
                'country' => $foreignCustomer->country,
                'color' => $foreignCustomer->color,
                'comment' => $foreignCustomer->comment,
                'created_by' => Auth::id(),
            ]);

            // Projekte einzeln speichern, damit die Project-saved-Hooks
            // (Kunden-Cascade auf DiaryEntries/Draft-Invoices) auslösen.
            foreach ($foreignCustomer->projects()->get() as $project) {
                $project->foreign_customer_id = null;
                $project->customer_id = $customer->id;
                $project->save();
            }

            $foreignCustomer->forceFill(['archived_at' => now()])->save();

            return $customer;
        });

        return redirect()->route('customers.show', $customer)
            ->with('success', __('Fremdkunde zu Kunde befördert. :count Projekt(e) übernommen.', [
                'count' => $customer->projects()->count(),
            ]));
    }

    /** Stellt sicher, dass der gewählte Kunde zur aktuellen Organisation gehört. */
    private function assertCustomerInOrganization(int $customerId): void {
        // Customer-Query ist über den OrganizationScope bereits mandantengebunden.
        abort_unless(Customer::query()->whereKey($customerId)->exists(), 422, __('Ungültiger Kunde.'));
    }
}
