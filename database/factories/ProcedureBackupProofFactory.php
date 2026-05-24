<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupProofFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Procedure\{ProcedureBackupScope, ProcedureBackupStorageTarget, ProcedureBackupVerifyMethod};
use App\Models\{ProcedureBackupProof, ProcedureStepRun};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcedureBackupProof>
 */
class ProcedureBackupProofFactory extends Factory {
    protected $model = ProcedureBackupProof::class;

    public function definition(): array {
        return [
            'procedure_step_run_id' => ProcedureStepRun::factory(),
            'backup_scope' => ProcedureBackupScope::Config->value,
            'source_label' => 'Demo-Backup',
            'taken_at' => now(),
            'size_bytes' => 4096,
            'checksum_algo' => 'sha256',
            'checksum_value' => str_repeat('a', 64),
            'storage_target' => ProcedureBackupStorageTarget::External->value,
            'attachment_id' => null,
            'external_ref' => '/srv/backup/demo.tar.gz',
            'verified' => false,
            'verified_at' => null,
            'verified_by_user_id' => null,
            'verify_method' => ProcedureBackupVerifyMethod::ManagerConfirmation->value,
            'verify_note' => null,
            'created_at' => now(),
        ];
    }
}
