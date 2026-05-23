<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupProofServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\ProcedureBackupScope;
use App\Enums\Procedure\ProcedureBackupStorageTarget;
use App\Enums\Procedure\ProcedureBackupVerifyMethod;
use App\Enums\Procedure\ProcedureRunEventType;
use App\Enums\Procedure\ProcedureStepRunStatus;
use App\Enums\Procedure\ProcedureStepType;
use App\Exceptions\ProcedureBackupValidationException;
use App\Exceptions\ProcedureStepBlockedException;
use App\Models\DiaryEntry;
use App\Models\Organization;
use App\Models\ProcedureBackupProof;
use App\Models\ProcedureStepRun;
use App\Models\ProcedureTemplate;
use App\Models\User;
use App\Services\Procedure\BackupProofService;
use App\Services\Procedure\ProcedureExecutionService;
use App\Services\Procedure\ProcedureTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupProofServiceTest extends TestCase {
    use RefreshDatabase;

    private ProcedureTemplateService $templates;

    private ProcedureExecutionService $executor;

    private BackupProofService $backups;

    protected function setUp(): void {
        parent::setUp();
        $this->templates = app(ProcedureTemplateService::class);
        $this->executor = app(ProcedureExecutionService::class);
        $this->backups = app(BackupProofService::class);
    }

    public function test_register_creates_proof_and_logs_event(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makeBackupTemplate($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $stepRun = $run->stepRuns->first();

        $proof = $this->backups->register($stepRun, $user, $this->validPayload());

        $this->assertInstanceOf(ProcedureBackupProof::class, $proof);
        $this->assertFalse($proof->verified);
        $this->assertSame(
            1,
            $run->events()->where('event_type', ProcedureRunEventType::BackupRegistered->value)->count(),
        );
    }

    public function test_register_rejects_when_too_small(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makeBackupTemplate($org, $user, ['min_size_kb' => 64]);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $stepRun = $run->stepRuns->first();

        try {
            $this->backups->register($stepRun, $user, $this->validPayload(['size_bytes' => 1024]));
            $this->fail('Expected ProcedureBackupValidationException');
        } catch (ProcedureBackupValidationException $e) {
            $this->assertSame(ProcedureBackupValidationException::REASON_TOO_SMALL, $e->reason);
        }
    }

    public function test_register_rejects_when_too_old(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makeBackupTemplate($org, $user, ['max_age_minutes' => 60]);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $stepRun = $run->stepRuns->first();

        try {
            $this->backups->register($stepRun, $user, $this->validPayload([
                'taken_at' => now()->subHours(3)->toDateTimeString(),
            ]));
            $this->fail('Expected ProcedureBackupValidationException');
        } catch (ProcedureBackupValidationException $e) {
            $this->assertSame(ProcedureBackupValidationException::REASON_TOO_OLD, $e->reason);
        }
    }

    public function test_release_blocks_when_not_verified(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makeBackupTemplate($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $stepRun = $run->stepRuns->first();

        $this->backups->register($stepRun, $user, $this->validPayload());

        try {
            $this->executor->execute($stepRun->fresh(), $user, ProcedureStepRunStatus::Done);
            $this->fail('Expected ProcedureStepBlockedException');
        } catch (ProcedureStepBlockedException $e) {
            $this->assertSame(ProcedureStepBlockedException::REASON_BACKUP_NOT_VERIFIED, $e->reason);
        }
    }

    public function test_verify_unlocks_backup_step_completion(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makeBackupTemplate($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $stepRun = $run->stepRuns->first();

        $proof = $this->backups->register($stepRun, $user, $this->validPayload());
        $verified = $this->backups->verify($proof, $user, null, 'okay');

        $this->assertTrue($verified->verified);
        $this->assertNotNull($verified->verified_at);

        $done = $this->executor->execute($stepRun->fresh(), $user, ProcedureStepRunStatus::Done);
        $this->assertSame(ProcedureStepRunStatus::Done, $done->status);
        $this->assertSame(
            1,
            $run->events()->where('event_type', ProcedureRunEventType::BackupVerified->value)->count(),
        );
    }

    public function test_verify_checksum_method_requires_matching_probe(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->makeBackupTemplate($org, $user);
        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        $stepRun = $run->stepRuns->first();

        $payload = $this->validPayload([
            'verify_method' => ProcedureBackupVerifyMethod::Checksum->value,
            'checksum_algo' => 'sha256',
            'checksum_value' => str_repeat('b', 64),
        ]);
        $proof = $this->backups->register($stepRun, $user, $payload);

        try {
            $this->backups->verify($proof, $user, 'wrong');
            $this->fail('Expected checksum mismatch');
        } catch (ProcedureBackupValidationException $e) {
            $this->assertSame(ProcedureBackupValidationException::REASON_CHECKSUM_MISMATCH, $e->reason);
        }

        $ok = $this->backups->verify($proof->fresh(), $user, str_repeat('b', 64));
        $this->assertTrue($ok->verified);
    }

    public function test_followup_step_blocked_when_prior_backup_missing(): void {
        [$org, $user] = $this->makeOrgAndUser();
        // Backup step + follow-up Confirm step that requires prior backup
        $template = $this->templates->create($org, $user, [
            'code' => 'BK-PRIOR-'.uniqid(),
            'name' => 'Backup + Maint',
        ]);
        $version = $template->versions->first();
        $this->templates->addStepDef($version, [
            'code' => 'backup',
            'step_type' => ProcedureStepType::Backup->value,
            'label' => 'Backup vor Wartung',
            'blocking' => false,
        ]);
        $this->templates->addStepDef($version, [
            'code' => 'maint',
            'step_type' => ProcedureStepType::Confirm->value,
            'label' => 'Wartung',
            'blocking' => false,
            'config' => [
                'requires_prior_backup' => true,
                'prior_backup_max_age_minutes' => 120,
            ],
        ]);
        $this->templates->publish($version, $user);
        $template = $template->fresh(['versions.steps']);

        $entry = DiaryEntry::factory()->for($user)->create();
        $run = $this->executor->start($template, $entry, $user);
        /** @var ProcedureStepRun $maint */
        $maint = $run->stepRuns->last();

        try {
            $this->executor->canExecute($maint, $user);
            $this->fail('Expected REASON_PRIOR_BACKUP_MISSING');
        } catch (ProcedureStepBlockedException $e) {
            $this->assertSame(ProcedureStepBlockedException::REASON_PRIOR_BACKUP_MISSING, $e->reason);
        }

        // After valid + verified backup, follow-up becomes executable
        /** @var ProcedureStepRun $backup */
        $backup = $run->stepRuns->first();
        $proof = $this->backups->register($backup, $user, $this->validPayload());
        $this->backups->verify($proof, $user, null);
        $this->executor->execute($backup->fresh(), $user, ProcedureStepRunStatus::Done);

        $this->assertTrue($this->executor->canExecute($maint->fresh(), $user));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array {
        return array_merge([
            'backup_scope' => ProcedureBackupScope::Config->value,
            'source_label' => 'Demo Backup',
            'taken_at' => now()->toDateTimeString(),
            'size_bytes' => 1024 * 256,
            'storage_target' => ProcedureBackupStorageTarget::External->value,
            'external_ref' => '/srv/backup/demo.tar.gz',
            'verify_method' => ProcedureBackupVerifyMethod::ManagerConfirmation->value,
        ], $overrides);
    }

    /** @param  array<string, mixed>  $stepConfig */
    private function makeBackupTemplate(Organization $org, User $user, array $stepConfig = []): ProcedureTemplate {
        $template = $this->templates->create($org, $user, [
            'code' => 'BK-'.uniqid(),
            'name' => 'Backup-Vorlage',
        ]);
        $version = $template->versions->first();
        $this->templates->addStepDef($version, [
            'code' => 'backup',
            'step_type' => ProcedureStepType::Backup->value,
            'label' => 'Backup erzeugen',
            'config' => $stepConfig,
        ]);
        $this->templates->publish($version, $user);

        return $template->fresh(['versions.steps']);
    }

    /** @return array{0: Organization, 1: User} */
    private function makeOrgAndUser(): array {
        $user = User::factory()->geschaeftsfuehrung()->create();

        return [$user->organization, $user];
    }
}
