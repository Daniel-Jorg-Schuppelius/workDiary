<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepDef.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Procedure\ProcedureProofType;
use App\Enums\Procedure\ProcedureStepType;
use Database\Factories\ProcedureStepDefFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Schritt-Definition einer Prozedurvorlagen-Version (MVP-025 §3.3).
 *
 * @property int $id
 * @property int $procedure_template_version_id
 * @property int $sort_order
 * @property string $code
 * @property ProcedureStepType $step_type
 * @property string $label
 * @property string|null $description
 * @property bool $required
 * @property bool $blocking
 * @property array<string, mixed>|null $config
 * @property string|null $required_role
 * @property string|null $required_qualification_code
 * @property bool $requires_second_person
 * @property ProcedureProofType|null $requires_proof_type
 */
class ProcedureStepDef extends Model {
    /** @use HasFactory<ProcedureStepDefFactory> */
    use HasFactory;

    protected $fillable = [
        'procedure_template_version_id',
        'sort_order',
        'code',
        'step_type',
        'label',
        'description',
        'required',
        'blocking',
        'config',
        'required_role',
        'required_qualification_code',
        'requires_second_person',
        'requires_proof_type',
    ];

    protected $casts = [
        'step_type' => ProcedureStepType::class,
        'requires_proof_type' => ProcedureProofType::class,
        'config' => 'array',
        'required' => 'bool',
        'blocking' => 'bool',
        'requires_second_person' => 'bool',
    ];

    /** @return BelongsTo<ProcedureTemplateVersion, $this> */
    public function version(): BelongsTo {
        return $this->belongsTo(ProcedureTemplateVersion::class, 'procedure_template_version_id');
    }
}
