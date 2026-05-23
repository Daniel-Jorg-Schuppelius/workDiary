<?php

/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureBackupProof.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procedure\ProcedureBackupScope;
use App\Enums\Procedure\ProcedureBackupStorageTarget;
use App\Enums\Procedure\ProcedureBackupVerifyMethod;
use Database\Factories\ProcedureBackupProofFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Backup-Nachweis fuer einen {@see ProcedureStepRun} vom Typ `backup`
 * (MVP-027 §3).
 *
 * @property int $id
 * @property int $procedure_step_run_id
 * @property ProcedureBackupScope $backup_scope
 * @property string $source_label
 * @property Carbon $taken_at
 * @property int $size_bytes
 * @property string|null $checksum_algo
 * @property string|null $checksum_value
 * @property ProcedureBackupStorageTarget $storage_target
 * @property int|null $attachment_id
 * @property string|null $external_ref
 * @property bool $verified
 * @property Carbon|null $verified_at
 * @property int|null $verified_by_user_id
 * @property ProcedureBackupVerifyMethod $verify_method
 * @property string|null $verify_note
 * @property Carbon $created_at
 */
class ProcedureBackupProof extends Model {
    public $timestamps = false;

    /** @use HasFactory<ProcedureBackupProofFactory> */
    use HasFactory;

    protected $fillable = [
        'procedure_step_run_id',
        'backup_scope',
        'source_label',
        'taken_at',
        'size_bytes',
        'checksum_algo',
        'checksum_value',
        'storage_target',
        'attachment_id',
        'external_ref',
        'verified',
        'verified_at',
        'verified_by_user_id',
        'verify_method',
        'verify_note',
        'created_at',
    ];

    protected $casts = [
        'backup_scope' => ProcedureBackupScope::class,
        'storage_target' => ProcedureBackupStorageTarget::class,
        'verify_method' => ProcedureBackupVerifyMethod::class,
        'taken_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'verified' => 'bool',
        'size_bytes' => 'int',
    ];

    /** @return BelongsTo<ProcedureStepRun, $this> */
    public function stepRun(): BelongsTo {
        return $this->belongsTo(ProcedureStepRun::class, 'procedure_step_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
