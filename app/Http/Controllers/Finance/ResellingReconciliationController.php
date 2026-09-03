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

use App\Enums\Reselling\{ReconciliationRunStatus, ReconciliationStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Jobs\Reselling\RunReconciliationJob;
use App\Models\Reselling\ReconciliationRun;
use App\Services\Reselling\Marketplace\ReconciliationCsvBuilder;
use App\Support\CsvExport;
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
            'window_after' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        if (! $request->hasFile('telekom') && ! $request->hasFile('qualityhosting')) {
            throw ValidationException::withMessages(['telekom' => (string) __('reselling.validation.need_file')]);
        }

        $run = ReconciliationRun::create([
            'organization_id' => $this->currentOrganizationId(),
            'created_by_user_id' => $request->user()?->id,
            'status' => ReconciliationRunStatus::Queued,
            'reference_date' => ($validated['reference_date'] ?? null) ?: CarbonImmutable::today()->toDateString(),
            'window_before' => (int) ($validated['window_before'] ?? 45),
            'window_after' => (int) ($validated['window_after'] ?? 90),
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

        return view('finance.reselling.show', [
            'run' => $run,
            'report' => $report,
            'findings' => $findings,
            'statusFilter' => $statusFilter,
            'companyFilter' => $companyFilter,
            'companies' => $companies,
            'statuses' => ReconciliationStatus::cases(),
        ]);
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
