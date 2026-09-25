<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureLibraryStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Procedure;

use App\Enums\Procedure\{ProcedureProofType, ProcedureStepType};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Bibliotheksschritt (Feature 026, MVP-896): einmal gepflegt, als Kopie in
 * Entwurfsversionen eingefügt — veröffentlichte Versionen bleiben unberührt.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property ProcedureStepType $step_kind
 * @property string $label
 * @property string|null $description
 * @property bool $is_required
 * @property bool $is_blocking
 * @property array<string, mixed>|null $config
 * @property string|null $required_role
 * @property string|null $required_qualification_code
 * @property bool $requires_second_person
 * @property ProcedureProofType|null $requires_proof_kind
 */
class ProcedureLibraryStep extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'step_kind',
        'label',
        'description',
        'is_required',
        'is_blocking',
        'config',
        'required_role',
        'required_qualification_code',
        'requires_second_person',
        'requires_proof_kind',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'step_kind' => ProcedureStepType::class,
        'requires_proof_kind' => ProcedureProofType::class,
        'config' => 'array',
        'is_required' => 'bool',
        'is_blocking' => 'bool',
        'requires_second_person' => 'bool',
    ];
}
