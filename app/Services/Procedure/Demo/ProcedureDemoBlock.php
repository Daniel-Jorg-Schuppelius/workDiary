<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure\Demo;

use App\Enums\Procedure\{ProcedureBackupScope, ProcedureBackupStorageTarget, ProcedureBackupVerifyMethod, ProcedureProofType, ProcedureStepRunStatus};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Procedure\{ProcedureRun, ProcedureTemplate};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Procedure\{BackupProofService, ProcedureExecutionService, ProcedureTemplateService, SecondPersonGate};
use Illuminate\Support\Collection;

/** Demo-Prozedurlauf am Hauptauftrag (Feature 040 Nachtrag), als Block des Prozedurmoduls (MVP-863). */
final class ProcedureDemoBlock implements DemoBlock {
    public function __construct(
        private readonly ProcedureTemplateService $templates,
        private readonly ProcedureExecutionService $executor,
        private readonly SecondPersonGate $gate,
        private readonly BackupProofService $backups,
    ) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->mainDiary !== null;
    }

    public function seed(DemoSeedContext $context): array {
        /** @var DiaryEntry $entry */
        $entry = $context->mainDiary;

        return ['procedure_runs' => $this->seedProcedureRun($context->organization, $entry, $context->users, $context->blueprint)];
    }

    public function purge(Organization $organization): void {
        // Step-Runs/Events/Backup-Proofs hängen per FK-Cascade am Lauf.
        ProcedureRun::query()->where('organization_id', $organization->id)->delete();
    }

    /**
     * Spielt einen vollständigen Prozedurlauf auf dem Hauptauftrag durch —
     * inkl. registriertem UND verifiziertem Backup-Proof sowie
     * Vier-Augen-Freigabe (SecondPersonGate) durch einen zweiten Demo-User.
     * Nutzt die Prozedurvorlagen des installierten Branchenprofils; ohne
     * veröffentlichte Vorlage wird still übersprungen (0).
     *
     * @param Collection<int, User> $users
     * @param array<string, mixed> $blueprint
     */
    private function seedProcedureRun(Organization $organization, DiaryEntry $entry, Collection $users, array $blueprint): int {
        $templates = $this->templates;
        $executor = $this->executor;
        $gate = $this->gate;
        $backups = $this->backups;

        // Bevorzugt die im Blueprint benannte Vorlage der Branche (MVP-710),
        // sonst die erste mit veröffentlichter Version.
        $preferredCode = (string) ($blueprint['procedure_code'] ?? '');
        $candidates = ProcedureTemplate::query()
            ->where('organization_id', $organization->id)
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$preferredCode])
            ->orderBy('id')
            ->get();
        $template = null;
        foreach ($candidates as $candidate) {
            if ($templates->currentVersionFor($candidate) !== null) {
                $template = $candidate;
                break;
            }
        }
        if (! $template instanceof ProcedureTemplate) {
            return 0;
        }

        /** @var User $executorUser Ausführender Techniker (zweiter Demo-User). */
        $executorUser = $users->skip(1)->first() ?? $users->first();
        /** @var User $approver Vier-Augen-Zweitperson/Verifizierer (Demo-Admin). */
        $approver = $users->first();

        $run = $executor->start($template, $entry, $approver, $executorUser);

        foreach ($run->stepRuns()->with('stepDef')->orderBy('id')->get() as $stepRun) {
            $def = $stepRun->stepDef;

            if ($def?->requires_proof_type === ProcedureProofType::Backup) {
                $proof = $backups->register($stepRun, $executorUser, [
                    'backup_scope' => ProcedureBackupScope::Config->value,
                    'source_label' => 'Demo-Konfigurationsbackup',
                    'taken_at' => now()->toDateTimeString(),
                    'size_bytes' => 1024 * 256,
                    'storage_target' => ProcedureBackupStorageTarget::External->value,
                    'external_ref' => '/srv/backup/demo-config.tar.gz',
                    'verify_method' => ProcedureBackupVerifyMethod::ManagerConfirmation->value,
                ]);
                $backups->verify($proof, $approver, null, 'Demo: Backup geprüft.');
            }

            $fresh = $stepRun->fresh();
            if ($fresh !== null && $gate->requiresSecondPerson($fresh)) {
                $gate->request($fresh, $executorUser);
                $gate->take($fresh->fresh() ?? $fresh, $approver);
                $gate->sign($fresh->fresh() ?? $fresh, $approver);
            }

            $fresh = $stepRun->fresh();
            if ($fresh !== null) {
                $executor->execute($fresh, $executorUser, ProcedureStepRunStatus::Done, [
                    'note' => 'Demo-Durchlauf',
                ]);
            }
        }

        $completed = $run->fresh();
        if ($completed instanceof ProcedureRun) {
            $executor->completeRun($completed, $executorUser);
        }

        return 1;
    }
}
