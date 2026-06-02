<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataTransferController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Export\{ExportEntity, ExportFormat, ExportRunState};
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, ExportRun, ImportRun, Organization, User};
use App\Services\Export\{ExportRunner, ExportSpecRegistry};
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Storage};
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Datentransfer — zentraler Im-/Export-Bereich (Admin).
 *
 * Bündelt den CSV-Import-Wizard ({@see ImportController}) und einen
 * neuen Export-Bereich unter einer gemeinsamen Tab-Navigation
 * (Import · Export · Verlauf).
 */
class DataTransferController extends Controller {
    public function __construct(
        private readonly ExportSpecRegistry $registry,
        private readonly ExportRunner $runner,
    ) {}

    /** Export-Tab: Formular + jüngste Export-Läufe. */
    public function index(Request $request): View {
        $this->authorizeHub();
        $organization = $this->currentOrganization();
        $entity = ExportEntity::tryFrom($request->string('entity')->toString()) ?? ExportEntity::Customers;

        $runs = ExportRun::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        return view('admin.data.index', [
            'organization' => $organization,
            'entity' => $entity,
            'entities' => ExportEntity::cases(),
            'formats' => ExportFormat::cases(),
            'runs' => $runs,
        ]);
    }

    /** Verlauf-Tab: Import- und Export-Läufe gemeinsam. */
    public function history(Request $request): View {
        $this->authorizeHub();
        $organization = $this->currentOrganization();

        $imports = ImportRun::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $exports = ExportRun::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('admin.data.history', [
            'organization' => $organization,
            'imports' => $imports,
            'exports' => $exports,
        ]);
    }

    public function export(Request $request): RedirectResponse {
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'entity' => ['required', 'string'],
            'format' => ['required', 'string'],
            'status' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $entity = ExportEntity::from($data['entity']);
        $format = ExportFormat::from($data['format']);
        $this->authorizeExport($entity);

        $filters = array_filter([
            'status' => $data['status'] ?? null,
            'q' => $data['q'] ?? null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'user_id' => $data['user_id'] ?? null,
        ], static fn($v): bool => $v !== null && $v !== '');

        $run = $this->runner->run(
            $this->registry->for($entity),
            $organization,
            $filters,
            $format,
            Auth::user(),
        );

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => Auth::id(),
            'event' => $run->state === ExportRunState::Ready ? 'export.created' : 'export.failed',
            'auditable_type' => ExportRun::class,
            'auditable_id' => $run->id,
            'changes' => [
                'entity' => $entity->value,
                'format' => $format->value,
                'rows_total' => $run->rows_total,
            ],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        if ($run->state !== ExportRunState::Ready) {
            return redirect()->route('admin.data.index', ['entity' => $entity->value])
                ->withErrors(['export' => __('Export fehlgeschlagen: :msg', ['msg' => $run->error_message ?? ''])]);
        }

        return redirect()->route('admin.data.download', $run)
            ->with('success', __(':count Datensätze exportiert.', ['count' => $run->rows_total]));
    }

    public function download(Request $request, ExportRun $export): StreamedResponse {
        $this->ensureOwned($export);
        abort_unless($export->state === ExportRunState::Ready, 409);

        $disk = Storage::disk(ExportRunner::DISK);
        abort_unless($disk->exists($export->storage_path), 404);

        $stream = $disk->readStream($export->storage_path);
        abort_if($stream === null, 404);

        return response()->streamDownload(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, $export->output_filename, [
            'Content-Type' => $export->format->mime(),
        ]);
    }

    public function destroy(Request $request, ExportRun $export): RedirectResponse {
        $this->ensureOwned($export);

        $disk = Storage::disk(ExportRunner::DISK);
        if ($export->storage_path !== '' && $disk->exists($export->storage_path)) {
            $disk->delete($export->storage_path);
        }
        $export->delete();

        return redirect()->route('admin.data.index')
            ->with('success', __('Export wurde gelöscht.'));
    }

    private function currentOrganization(): Organization {
        abort_unless(app()->bound('currentOrganization'), 403);
        $organization = app('currentOrganization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    private function ensureOwned(ExportRun $run): void {
        abort_unless($run->organization_id === $this->currentOrganization()->id, 403);
    }

    private function authorizeExport(ExportEntity $entity): void {
        $user = Auth::user();
        abort_unless(
            $user instanceof User && ($user->isAdmin() || $user->hasEffectivePermission($entity->permission())),
            403
        );
    }

    /** Zugang zum Datentransfer-Bereich: Admin oder mindestens ein Export-Recht. */
    private function authorizeHub(): void {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        if ($user->isAdmin()) {
            return;
        }

        foreach (ExportEntity::cases() as $entity) {
            if ($user->hasEffectivePermission($entity->permission())) {
                return;
            }
        }

        abort(403);
    }
}
