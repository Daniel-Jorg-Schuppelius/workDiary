<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Procedure;

use App\Enums\Procedure\{ProcedureBackupScope, ProcedureBackupStorageTarget, ProcedureBackupVerifyMethod, ProcedureStepType};
use App\Exceptions\ProcedureBackupValidationException;
use App\Http\Controllers\Controller;
use App\Models\Procedure\{ProcedureBackupProof, ProcedureRun, ProcedureStepRun};
use App\Services\Attachments\FileAttacher;
use App\Services\Procedure\BackupProofService;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/**
 * Backup-Nachweis eines Prozedurschritts erfassen, prüfen und ablehnen
 * (Feature 026, MVP-1566). Die Regeln stehen im {@see BackupProofService}.
 */
class ProcedureBackupController extends Controller {
    private const array FIELD_BY_REASON = [
        ProcedureBackupValidationException::REASON_TOO_SMALL => 'size_kb',
        ProcedureBackupValidationException::REASON_TOO_OLD => 'taken_at',
        ProcedureBackupValidationException::REASON_CHECKSUM_MISMATCH => 'checksum_value',
        ProcedureBackupValidationException::REASON_CHECKSUM_MISSING => 'checksum_value',
        ProcedureBackupValidationException::REASON_MISSING_EXTERNAL_REF => 'external_ref',
        ProcedureBackupValidationException::REASON_MISSING_ATTACHMENT => 'proof',
    ];

    public function __construct(private readonly BackupProofService $backups) {}

    public function form(ProcedureRun $run, ProcedureStepRun $stepRun): View {
        $this->authorizeRegister($run, $stepRun);

        return view('procedures.runs._backup_dialog', [
            'run' => $run,
            'stepRun' => $stepRun,
            'fixed' => $this->fixedValues($stepRun),
            'config' => (array) ($stepRun->stepDef->config ?? []),
        ]);
    }

    public function store(Request $request, ProcedureRun $run, ProcedureStepRun $stepRun): RedirectResponse {
        $this->authorizeRegister($run, $stepRun);

        $request->merge($this->fixedValues($stepRun));
        $data = $request->validate([
            'backup_scope' => ['required', Rule::enum(ProcedureBackupScope::class)],
            'source_label' => ['required', 'string', 'max:180'],
            'taken_at' => ['required', 'date'],
            'storage_target' => ['required', Rule::enum(ProcedureBackupStorageTarget::class)],
            'verify_method' => ['required', Rule::enum(ProcedureBackupVerifyMethod::class)],
            'checksum_value' => ['nullable', 'string', 'max:128'],
            'external_ref' => ['nullable', 'string', 'max:255', 'required_if:storage_target,' . ProcedureBackupStorageTarget::External->value],
            'size_kb' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'proof' => array_merge(['nullable', 'required_if:storage_target,' . ProcedureBackupStorageTarget::Attachment->value], FileAttacher::rule()),
            'verify_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $request->file('proof');
        $attached = $data['storage_target'] === ProcedureBackupStorageTarget::Attachment->value && $file instanceof UploadedFile;
        $algorithm = HashAlgorithm::tryFrom(mb_strtolower((string) ($stepRun->stepDef?->config['checksum_algo'] ?? ''))) ?? HashAlgorithm::SHA256;

        $payload = [
            'backup_scope' => $data['backup_scope'],
            'source_label' => $data['source_label'],
            'taken_at' => $data['taken_at'],
            'size_bytes' => $attached ? (int) $file->getSize() : (int) ($data['size_kb'] ?? 0) * 1024,
            'checksum_algo' => $algorithm->value,
            'checksum_value' => $data['checksum_value'] ?? null,
            'file_checksum' => $attached ? File::hash((string) $file->getRealPath(), $algorithm) : null,
            'storage_target' => $data['storage_target'],
            'external_ref' => $attached ? null : ($data['external_ref'] ?? null),
            'verify_method' => $data['verify_method'],
            'verify_note' => $data['verify_note'] ?? null,
        ];

        try {
            $this->backups->assertRegistrable($stepRun, $payload);
            if ($attached) {
                // Träger ist der Lauf: seine Policy entscheidet über den Abruf der Datei.
                $payload['attachment_id'] = (int) app(FileAttacher::class)->store($run, $file, (int) $this->authUser()->id, [
                    'organization_id' => $run->organization_id,
                    'meta_type' => 'procedure_backup',
                ], 'attachments/procedure-runs')->id;
            }
            $proof = $this->backups->register($stepRun, $this->authUser(), $payload);
        } catch (ProcedureBackupValidationException $e) {
            throw ValidationException::withMessages([
                self::FIELD_BY_REASON[$e->reason] ?? 'source_label' => __('procedure.backup.reason.' . $e->reason),
            ]);
        }

        return redirect()->route('procedure-runs.show', $run)
            ->with('success', $proof->verified ? __('procedure.flash.backupVerified') : __('procedure.flash.backupRegistered'));
    }

    public function verify(Request $request, ProcedureRun $run, ProcedureStepRun $stepRun): RedirectResponse {
        $proof = $this->reviewableProof($run, $stepRun);

        $data = $request->validate([
            'checksum_probe' => ['nullable', 'string', 'max:128'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->backups->verify($proof, $this->authUser(), $data['checksum_probe'] ?? null, $data['note'] ?? null);
        } catch (ProcedureBackupValidationException $e) {
            return back()->with('error', __('procedure.backup.reason.' . $e->reason));
        }

        return back()->with('success', __('procedure.flash.backupVerified'));
    }

    public function reject(Request $request, ProcedureRun $run, ProcedureStepRun $stepRun): RedirectResponse {
        $proof = $this->reviewableProof($run, $stepRun);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['reason' => __('procedure.backup.reject_reason')]);

        $this->backups->reject($proof, $this->authUser(), $data['reason']);

        return back()->with('success', __('procedure.flash.backupRejected'));
    }

    private function authorizeRegister(ProcedureRun $run, ProcedureStepRun $stepRun): void {
        Gate::authorize('execute', $run);
        Gate::authorize('register', [ProcedureBackupProof::class, $stepRun]);
        $this->assertOpenBackupStep($run, $stepRun);
    }

    private function reviewableProof(ProcedureRun $run, ProcedureStepRun $stepRun): ProcedureBackupProof {
        Gate::authorize('view', $run);
        $this->assertOpenBackupStep($run, $stepRun);

        $proof = ProcedureBackupProof::query()->where('procedure_step_run_id', $stepRun->id)->first();
        abort_if(! $proof instanceof ProcedureBackupProof || $proof->verified, 409);
        Gate::authorize('verify', $proof);

        return $proof;
    }

    private function assertOpenBackupStep(ProcedureRun $run, ProcedureStepRun $stepRun): void {
        abort_unless((int) $stepRun->procedure_run_id === (int) $run->id, 404);
        abort_unless($stepRun->stepDef?->step_type === ProcedureStepType::Backup, 404);
        abort_unless($run->status->isActive() && ! $stepRun->status->isFinal(), 409);
    }

    /**
     * Umfang, Ablage und Prüfweg legt die Vorlage fest, wenn der Schritt sie nennt.
     *
     * @return array<string, string>
     */
    private function fixedValues(ProcedureStepRun $stepRun): array {
        $config = (array) ($stepRun->stepDef->config ?? []);

        return array_filter([
            'backup_scope' => ProcedureBackupScope::tryFrom((string) ($config['backup_scope'] ?? ''))?->value,
            'storage_target' => ProcedureBackupStorageTarget::tryFrom((string) ($config['storage_target'] ?? ''))?->value,
            'verify_method' => ProcedureBackupVerifyMethod::tryFrom((string) ($config['verify_method'] ?? ''))?->value,
        ]);
    }
}
