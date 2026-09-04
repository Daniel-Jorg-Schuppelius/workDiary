<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResellingReconciliationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Reselling\{CompanyMappingMode, ReconciliationRunStatus, ReconciliationStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Jobs\Reselling\RunReconciliationJob;
use App\Models\{Customer, ForeignCustomer};
use App\Models\Reselling\{CompanyMapping, ReconciliationRun};
use App\Services\Reselling\Marketplace\{MarketplaceCompany, ReconciliationCsvBuilder};
use App\Support\{CsvExport, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Feature 151 (MVP-757): Lizenz-Reselling-Abgleich aus der Oberfläche. Der
 * Controller nimmt nur Dateien und Optionen entgegen und zeigt gespeicherte
 * Berichte; gerechnet wird im Job (`RunReconciliationJob`). Recht:
 * `can:finance.reselling.manage` (Route-Middleware).
 */
class ResellingReconciliationController extends Controller {
    use ResolvesCurrentOrganization;

    private const MAX_FILE_KB = 20480;

    public function index(): View {
        $runs = ReconciliationRun::query()
            ->with('creator')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('finance.reselling.index', compact('runs'));
    }

    public function create(): View {
        return view('finance.reselling._form_dialog', [
            'defaultReference' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $validated = $request->validate([
            'telekom' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:csv,txt'],
            'qualityhosting' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:xlsx,xlsm'],
            'pricelist' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:xlsx,xlsm'],
            'map' => ['nullable', 'file', 'max:1024', 'extensions:csv,txt'],
            'reference_date' => ['nullable', 'date_format:Y-m-d'],
            'window_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'window_after' => ['nullable', 'integer', 'min:0', 'max:1825'],
            'strict_products' => ['nullable', 'boolean'],
        ]);

        if (! $request->hasFile('telekom') && ! $request->hasFile('qualityhosting')) {
            throw ValidationException::withMessages(['telekom' => (string) __('reselling.validation.need_file')]);
        }

        $run = ReconciliationRun::create([
            'organization_id' => $this->currentOrganizationId(),
            'created_by_user_id' => $request->user()?->id,
            'status' => ReconciliationRunStatus::Queued,
            'reference_date' => ($validated['reference_date'] ?? null) ?: CarbonImmutable::today()->toDateString(),
            'window_before' => (int) ($validated['window_before'] ?? \App\Services\Reselling\Marketplace\ReconciliationOptions::DEFAULT_BEFORE),
            'window_after' => (int) ($validated['window_after'] ?? \App\Services\Reselling\Marketplace\ReconciliationOptions::DEFAULT_AFTER),
            'strict_products' => (bool) ($validated['strict_products'] ?? false),
            'files' => [],
        ]);

        $files = [];
        foreach ([ReconciliationRun::KIND_TELEKOM, ReconciliationRun::KIND_QUALITYHOSTING, ReconciliationRun::KIND_PRICELIST, ReconciliationRun::KIND_MAP] as $kind) {
            $upload = $request->file($kind);
            if (! $upload instanceof UploadedFile) {
                continue;
            }
            $extension = strtolower($upload->getClientOriginalExtension() ?: 'bin');
            $stored = Storage::disk(ReconciliationRun::DISK)->putFileAs($run->storageDirectory(), $upload, $kind . '.' . $extension);
            $files[] = [
                'kind' => $kind,
                'name' => $upload->getClientOriginalName(),
                'path' => is_string($stored) ? $stored : $run->storageDirectory() . '/' . $kind . '.' . $extension,
            ];
        }
        $run->files = $files;
        $run->save();

        RunReconciliationJob::dispatch($run->id);

        return redirect()
            ->route('finance.reselling.show', $run->sqid)
            ->with('success', __('reselling.flash.created'));
    }

    public function show(Request $request, ReconciliationRun $run): View {
        $report = $run->report ?? [];
        $statusFilter = (string) $request->query('status', 'problems');
        $companyFilter = (string) $request->query('company', '');
        $allowed = array_merge(['problems', 'all'], array_map(static fn(ReconciliationStatus $s): string => $s->value, ReconciliationStatus::cases()));
        if (! in_array($statusFilter, $allowed, true)) {
            $statusFilter = 'problems';
        }

        $findings = array_values(array_filter((array) ($report['findings'] ?? []), static function (array $finding) use ($statusFilter, $companyFilter): bool {
            if ($companyFilter !== '' && ($finding['company_key'] ?? '') !== $companyFilter) {
                return false;
            }

            return match ($statusFilter) {
                'all' => true,
                'problems' => (bool) ($finding['problem'] ?? false),
                default => ($finding['status'] ?? '') === $statusFilter,
            };
        }));

        $companies = [];
        foreach ((array) ($report['mappings'] ?? []) as $mapping) {
            $companies[(string) $mapping['key']] = (string) $mapping['company'];
        }

        $lines = array_values(array_filter((array) ($report['lines'] ?? []), static fn(array $line): bool => $companyFilter === '' || ($line['company_key'] ?? '') === $companyFilter));

        $stored = CompanyMapping::query()->with('customer')->get()->keyBy('normalized_name');

        return view('finance.reselling.show', [
            'run' => $run,
            'report' => $report,
            'findings' => $findings,
            'statusFilter' => $statusFilter,
            'companyFilter' => $companyFilter,
            'companies' => $companies,
            'statuses' => ReconciliationStatus::cases(),
            'storedMappings' => $stored,
            'lines' => $lines,
        ]);
    }

    /**
     * Denselben Lauf mit den gespeicherten Zuordnungen neu rechnen — die
     * Dateien liegen noch am Lauf, ein neuer Upload ist nicht nötig.
     */
    public function rerun(ReconciliationRun $run): RedirectResponse {
        if (! $run->status->isFinished()) {
            return redirect()->route('finance.reselling.show', $run->sqid)->with('error', __('reselling.flash.not_done'));
        }

        // Das Suchfenster ist eine Heuristik, kein Nutzerentscheid: Ein Lauf aus
        // der Zeit engerer Vorgaben (45/90 Tage) fände späte Abrechnungen und
        // Mehrjahresblöcke nicht — beim Neuberechnen mindestens die aktuellen Vorgaben.
        $run->forceFill([
            'status' => ReconciliationRunStatus::Queued,
            'summary' => null,
            'report' => null,
            'error' => null,
            'started_at' => null,
            'finished_at' => null,
            'window_before' => max((int) $run->window_before, \App\Services\Reselling\Marketplace\ReconciliationOptions::DEFAULT_BEFORE),
            'window_after' => max((int) $run->window_after, \App\Services\Reselling\Marketplace\ReconciliationOptions::DEFAULT_AFTER),
        ])->save();

        RunReconciliationJob::dispatch($run->id);

        return redirect()->route('finance.reselling.show', $run->sqid)->with('success', __('reselling.flash.rerun'));
    }

    public function mappingCreate(Request $request, ReconciliationRun $run): View {
        $companyName = trim((string) $request->query('company', ''));
        $companyKey = trim((string) $request->query('key', ''));
        $existing = $companyName === '' ? null : CompanyMapping::query()->where('normalized_name', MarketplaceCompany::normalizeName($companyName))->first();

        return view('finance.reselling._mapping_dialog', [
            'run' => $run,
            'companyName' => $companyName,
            'companyKey' => $companyKey,
            'existing' => $existing,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'company']),
            'modes' => CompanyMappingMode::cases(),
        ]);
    }

    public function mappingStore(Request $request, ReconciliationRun $run): RedirectResponse {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_key' => ['nullable', 'string', 'max:64'],
            'mode' => ['required', 'string', 'in:' . implode(',', array_map(static fn(CompanyMappingMode $m): string => $m->value, CompanyMappingMode::cases()))],
            'customer' => ['nullable', 'string', 'max:64'],
            'contact_external_id' => ['nullable', 'string', 'max:64', 'regex:/^[0-9a-fA-F-]{36}$/'],
        ]);
        $mode = CompanyMappingMode::from($validated['mode']);

        $customer = null;
        if ($mode !== CompanyMappingMode::Contact) {
            $customerId = Sqid::decode(Customer::class, (string) ($validated['customer'] ?? ''));
            $customer = $customerId === null ? null : Customer::query()->find($customerId);
            if (! $customer instanceof Customer) {
                throw ValidationException::withMessages(['customer' => (string) __('reselling.validation.customer_required')]);
            }
            // Partner = Fremdkunde: den Endkunden auch im Kundenstamm unter dem
            // Partner führen, damit Projekte/Zeiten dieselbe Beziehung sehen.
            if ($mode === CompanyMappingMode::Partner) {
                $this->ensureForeignCustomer($customer, $validated['company_name'], $request->user()?->id);
            }
        } elseif (($validated['contact_external_id'] ?? '') === '') {
            throw ValidationException::withMessages(['contact_external_id' => (string) __('reselling.validation.contact_required')]);
        }

        $mapping = CompanyMapping::query()->firstOrNew([
            'organization_id' => $this->currentOrganizationId(),
            'normalized_name' => MarketplaceCompany::normalizeName($validated['company_name']),
        ]);
        $mapping->fill([
            'company_name' => $validated['company_name'],
            'company_key' => ($validated['company_key'] ?? '') !== '' ? $validated['company_key'] : $mapping->company_key,
            'mode' => $mode,
            'customer_id' => $customer?->id,
            'contact_external_id' => $mode === CompanyMappingMode::Contact ? strtolower((string) $validated['contact_external_id']) : null,
            'created_by_user_id' => $request->user()?->id,
        ]);
        $mapping->save();

        return redirect()->route('finance.reselling.show', $run->sqid)->with('success', __('reselling.flash.mapping_saved'));
    }

    private function ensureForeignCustomer(Customer $partner, string $companyName, ?int $userId): void {
        $wanted = MarketplaceCompany::normalizeName($companyName);
        $exists = ForeignCustomer::query()->where('customer_id', $partner->id)->get(['id', 'name', 'company'])
            ->contains(static fn(ForeignCustomer $f): bool => MarketplaceCompany::normalizeName((string) $f->name) === $wanted
                || MarketplaceCompany::normalizeName((string) ($f->company ?? '')) === $wanted);
        if ($exists) {
            return;
        }

        ForeignCustomer::create([
            'organization_id' => $this->currentOrganizationId(),
            'customer_id' => $partner->id,
            'name' => $companyName,
            'company' => $companyName,
            'created_by' => $userId,
        ]);
    }

    public function mappingDestroy(ReconciliationRun $run, CompanyMapping $mapping): RedirectResponse {
        $mapping->delete();

        return redirect()->route('finance.reselling.show', $run->sqid)->with('success', __('reselling.flash.mapping_removed'));
    }

    public function download(ReconciliationRun $run, ReconciliationCsvBuilder $csv): StreamedResponse|RedirectResponse {
        if ($run->status !== ReconciliationRunStatus::Done || $run->report === null) {
            return redirect()->route('finance.reselling.show', $run->sqid)->with('error', __('reselling.flash.not_done'));
        }

        $filename = 'lizenz-abgleich-' . $run->reference_date->format('Y-m-d') . '-' . $run->sqid . '.csv';

        return CsvExport::streamFromRows($filename, $csv->header(), $csv->rows($run->report), ';');
    }

    public function destroy(ReconciliationRun $run): RedirectResponse {
        $run->delete();

        return redirect()->route('finance.reselling.index')->with('success', __('reselling.flash.deleted'));
    }
}
