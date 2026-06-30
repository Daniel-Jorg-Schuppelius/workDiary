<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Import\{ImportEntity, ImportRunState};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCsvImportJob;
use App\Models\{AuditLog, ImportRun, ImportRunError, User};
use App\Services\Import\CsvPreflightAnalyzer;
use App\Support\Toolkit\CsvFacade;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\{Auth, Storage};

/**
 * MVP-049 — CSV-Import-Wizard (Admin).
 *
 * Workflow: index → create (entity) → preflight (POST upload) → show
 * (Vorschau + Fehler) → confirm (Dispatch Job) → show (Status).
 */
class ImportController extends Controller {
    use ResolvesCurrentOrganization;

    private const ALLOWED_SORTS = ['id', 'entity', 'input_filename', 'state', 'rows_total', 'created_at'];

    public function __construct(private readonly CsvPreflightAnalyzer $analyzer) {}

    public function index(Request $request): View {
        $organization = $this->currentOrganization();

        $filters = [
            'entity' => $request->string('entity')->toString(),
            'state' => $request->string('state')->toString(),
        ];

        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'id';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $runs = ImportRun::query()
            ->where('organization_id', $organization->id)
            ->when($filters['entity'] !== '', fn($q) => $q->where('entity', $filters['entity']))
            ->when($filters['state'] !== '', fn($q) => $q->where('state', $filters['state']))
            ->orderBy($sort, $dir)
            ->paginate(25)
            ->withQueryString();

        return view('admin.imports.index', [
            'runs' => $runs,
            'filters' => $filters,
            'entities' => ImportEntity::cases(),
            'states' => ImportRunState::cases(),
            'organization' => $organization,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View {
        $organization = $this->currentOrganization();
        $entity = ImportEntity::tryFrom($request->string('entity', 'customers')->toString()) ?? ImportEntity::Customers;
        $this->authorizeImport($entity);

        $supportsInboxFirst = app(\App\Services\Import\EntitySpecRegistry::class)->for($entity)
            instanceof \App\Services\Import\InboxFirstSpec;

        return view('admin.imports.create', [
            'organization' => $organization,
            'entity' => $entity,
            'entities' => ImportEntity::cases(),
            'supportsInboxFirst' => $supportsInboxFirst,
        ]);
    }

    public function preflight(Request $request): RedirectResponse {
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'entity' => ['required', 'string'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:' . (CsvPreflightAnalyzer::MAX_BYTES / 1024)],
            'match_policy' => ['nullable', 'in:auto_create,inbox_first'],
        ]);
        $entity = ImportEntity::from($data['entity']);
        $this->authorizeImport($entity);

        $run = $this->analyzer->analyze(
            $request->file('file'),
            $entity,
            $organization,
            Auth::user(),
            (string) ($data['match_policy'] ?? 'auto_create'),
        );

        if ($run->state === ImportRunState::Failed) {
            AuditLog::create([
                'organization_id' => $organization->id,
                'user_id' => Auth::id(),
                'event' => 'import.preflightFailed',
                'auditable_type' => ImportRun::class,
                'auditable_id' => $run->id,
                'changes' => ['entity' => $entity->value],
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return redirect()->route('admin.imports.show', $run);
    }

    public function show(Request $request, ImportRun $import): View {
        $this->ensureOwned($import);

        $errors = $import->errors()
            ->orderBy('row_number')
            ->orderBy('id')
            ->paginate(50, ['*'], 'errors_page')
            ->withQueryString();

        return view('admin.imports.show', [
            'run' => $import,
            'errors' => $errors,
        ]);
    }

    public function confirm(Request $request, ImportRun $import): RedirectResponse {
        $this->ensureOwned($import);
        $this->authorizeImport($import->entity);
        abort_unless($import->state === ImportRunState::AwaitingApproval, 409);

        AuditLog::create([
            'organization_id' => $import->organization_id,
            'user_id' => Auth::id(),
            'event' => 'import.confirmed',
            'auditable_type' => ImportRun::class,
            'auditable_id' => $import->id,
            'changes' => [
                'entity' => $import->entity->value,
                'rows_total' => $import->rows_total,
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        ProcessCsvImportJob::dispatch($import->id);

        return redirect()->route('admin.imports.show', $import)
            ->with('success', __('Import wurde gestartet.'));
    }

    public function destroy(Request $request, ImportRun $import): RedirectResponse {
        $this->ensureOwned($import);
        abort_unless($import->state === ImportRunState::AwaitingApproval || $import->state === ImportRunState::Failed, 409);

        if ($import->storage_path !== '' && Storage::disk(CsvPreflightAnalyzer::DISK)->exists($import->storage_path)) {
            Storage::disk(CsvPreflightAnalyzer::DISK)->delete($import->storage_path);
        }
        $import->delete();

        return redirect()->route('admin.imports.index')
            ->with('success', __('Import wurde verworfen.'));
    }

    public function downloadErrors(Request $request, ImportRun $import): Response {
        $this->ensureOwned($import);

        $rows = [];
        foreach ($import->errors()->orderBy('row_number')->orderBy('id')->cursor() as $err) {
            /** @var ImportRunError $err */
            $rows[] = [
                'row' => $err->row_number,
                'field' => $err->field ?? '',
                'code' => $err->code->value,
                'message' => $err->message,
            ];
        }

        $csv = CsvFacade::buildCsv(['row', 'field', 'code', 'message'], $rows, ';');
        $filename = sprintf('errors_%d.csv', $import->id);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function ensureOwned(ImportRun $run): void {
        abort_unless($run->organization_id === $this->currentOrganization()->id, 403);
    }

    private function authorizeImport(ImportEntity $entity): void {
        $user = Auth::user();
        abort_unless(
            $user instanceof User && ($user->isAdmin() || $user->hasEffectivePermission($entity->permission())),
            403
        );
    }
}
