<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupHttpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureRunEventType, ProcedureStepRunStatus, ProcedureStepType};
use App\Enums\User\Permission;
use App\Models\Attachments\Attachment;
use App\Models\Procedure\ProcedureBackupProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Procedure\Concerns\BuildsProcedureRuns;
use Tests\TestCase;

/** Backup-Nachweis in der Lauf-Ansicht erfassen, prüfen und ablehnen (MVP-1566). */
class ProcedureBackupHttpTest extends TestCase {
    use BuildsProcedureRuns;
    use RefreshDatabase;

    public function test_backup_step_is_recorded_confirmed_by_a_colleague_and_completed(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [
            ['code' => 'backup', 'step_type' => ProcedureStepType::Backup->value, 'label' => 'Konfiguration sichern'],
            ['code' => 'update', 'config' => ['requires_prior_backup' => true]],
        ]), $lead);
        $backup = $this->stepOf($run, 'backup');

        $this->actingAs($lead)->get(route('procedure-runs.show', $run))
            ->assertOk()
            ->assertSee(__('procedure.backup.status.missing'))
            ->assertSee(route('procedure-runs.steps.backup.form', [$run, $backup]))
            ->assertDontSee(__('procedure.blocked.backupNotVerified'));
        $this->actingAs($lead)->get(route('procedure-runs.steps.backup.form', [$run, $backup]))
            ->assertOk()
            ->assertSee(__('procedure.backup.source'));

        $this->actingAs($lead)->post(route('procedure-runs.steps.backup.store', [$run, $backup]), [
            'backup_scope' => 'config',
            'source_label' => 'Router Werkstatt',
            'taken_at' => now()->format('Y-m-d\TH:i'),
            'storage_target' => 'external',
            'external_ref' => '/srv/backup/router.tar.gz',
            'size_kb' => 512,
            'verify_method' => 'managerConfirmation',
        ])->assertRedirect(route('procedure-runs.show', $run));

        $proof = ProcedureBackupProof::query()->where('procedure_step_run_id', $backup->id)->firstOrFail();
        $this->assertSame(512 * 1024, $proof->size_bytes?->getBytes());
        $this->assertSame($lead->id, $proof->registered_by_user_id);

        // Wer erfasst hat, bestätigt nicht selbst.
        $this->actingAs($lead)->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.steps.backup.verify', [$run, $backup]))
            ->assertSessionHas('error', __('procedure.backup.reason.selfVerify'));
        $this->assertFalse($proof->fresh()->verified);

        $colleague = $this->colleagueOf($lead);
        $this->actingAs($colleague)->from(route('procedure-runs.show', $run))
            ->post(route('procedure-runs.steps.backup.verify', [$run, $backup]), ['note' => 'Rückspielung geprüft'])
            ->assertSessionHas('success');
        $this->assertTrue($proof->fresh()->verified);

        $this->actingAs($lead)->post(route('procedure-runs.steps.execute', [$run, $backup]), ['status' => 'done'])->assertSessionHas('success');
        $this->assertSame(ProcedureStepRunStatus::Done, $backup->fresh()->status);
        // Der Folgeschritt verlangt das vorherige Backup und ist jetzt frei.
        $this->actingAs($lead)->post(route('procedure-runs.steps.execute', [$run, $this->stepOf($run, 'update')]), ['status' => 'done'])->assertSessionHas('success');
        $this->actingAs($lead)->post(route('procedure-runs.complete', $run))->assertSessionHas('success');
    }

    public function test_uploaded_file_with_matching_checksum_is_confirmed_automatically(): void {
        Storage::fake('local');
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [
            ['code' => 'backup', 'step_type' => ProcedureStepType::Backup->value, 'config' => ['verify_method' => 'checksum', 'storage_target' => 'attachment', 'backup_scope' => 'database']],
        ]), $lead);
        $backup = $this->stepOf($run, 'backup');
        $content = "dump der testdatenbank\n";
        $post = fn (string $checksum) => $this->actingAs($lead)->post(route('procedure-runs.steps.backup.store', [$run, $backup]), [
            'source_label' => 'Datenbank Lager',
            'taken_at' => now()->format('Y-m-d\TH:i'),
            'checksum_value' => $checksum,
            'proof' => UploadedFile::fake()->createWithContent('dump.txt', $content),
        ]);

        $post(str_repeat('0', 64))->assertSessionHasErrors('checksum_value');
        $this->assertSame(0, ProcedureBackupProof::query()->count());
        $this->assertSame(0, Attachment::query()->count());

        $post(hash('sha256', $content))->assertRedirect(route('procedure-runs.show', $run));
        $proof = ProcedureBackupProof::query()->firstOrFail();
        $this->assertTrue($proof->verified);
        $this->assertSame('database', $proof->backup_scope->value);
        $this->assertNotNull($proof->attachment);
        $this->assertTrue($run->is($proof->attachment->attachable));
        $this->assertSame(1, $run->journal()->where('event_type', ProcedureRunEventType::BackupVerified->value)->count());
    }

    public function test_rejecting_needs_a_reason_and_the_verify_permission(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [['code' => 'backup', 'step_type' => ProcedureStepType::Backup->value]]), $lead);
        $backup = $this->stepOf($run, 'backup');
        $this->actingAs($lead)->post(route('procedure-runs.steps.backup.store', [$run, $backup]), [
            'backup_scope' => 'config', 'source_label' => 'Firewall', 'taken_at' => now()->format('Y-m-d\TH:i'),
            'storage_target' => 'external', 'external_ref' => 'nas://sicherung/fw', 'verify_method' => 'restoreCheck',
        ])->assertRedirect();

        $worker = $this->colleagueOf($lead, 'user');
        $this->assertFalse($worker->can(Permission::ProcedureBackupVerify->value));
        $this->actingAs($worker)->post(route('procedure-runs.steps.backup.reject', [$run, $backup]), ['reason' => 'Datei fehlt'])->assertForbidden();
        // Ohne Recht auf externe Ablagen bleibt der Ablageort verborgen.
        $this->actingAs($worker)->get(route('procedure-runs.show', $run))->assertOk()
            ->assertDontSee('nas://sicherung/fw')
            ->assertSee(__('procedure.backup.external_hidden'));

        $colleague = $this->colleagueOf($lead);
        $this->actingAs($colleague)->post(route('procedure-runs.steps.backup.reject', [$run, $backup]), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($colleague)->post(route('procedure-runs.steps.backup.reject', [$run, $backup]), ['reason' => 'Datei fehlt auf dem Speicher'])->assertSessionHas('success');
        $this->assertNotNull(ProcedureBackupProof::query()->firstOrFail()->rejected_at);
        $this->actingAs($colleague)->get(route('procedure-runs.show', $run))->assertOk()
            ->assertSee('nas://sicherung/fw')
            ->assertSee(__('procedure.backup.status.rejected', ['name' => $colleague->name]));
    }

    public function test_recording_needs_the_register_permission_and_an_open_backup_step(): void {
        $lead = $this->lead();
        $run = $this->startRun($this->publishedTemplate($lead, [
            ['code' => 'backup', 'step_type' => ProcedureStepType::Backup->value],
            ['code' => 'other', 'blocking' => false],
        ]), $lead);

        $this->actingAs($lead)->get(route('procedure-runs.steps.backup.form', [$run, $this->stepOf($run, 'other')]))->assertNotFound();

        foreach ($lead->roles as $role) {
            $role->revokePermissionTo(Permission::ProcedureBackupRegister->value);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($lead->fresh())->get(route('procedure-runs.steps.backup.form', [$run, $this->stepOf($run, 'backup')]))->assertForbidden();
    }
}
