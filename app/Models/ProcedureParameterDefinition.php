<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureParameterDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Manufacturing\ParameterType;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typisierter, versionierter Auftragsparameter einer Arbeitsplan-Version
 * (Feature 047, MVP-061). Mandantengrenze transitiv über die Arbeitsplan-Version.
 *
 * @property int $id
 * @property int $procedure_template_version_id
 * @property string $code
 * @property string $label
 * @property ParameterType $type
 * @property array<string, mixed>|null $constraints
 * @property int $position
 * @property bool $active
 */
class ProcedureParameterDefinition extends Model {
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'procedure_template_version_id',
        'code',
        'label',
        'type',
        'constraints',
        'position',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'type' => ParameterType::class,
        'constraints' => 'array',
        'position' => 'integer',
        'active' => 'boolean',
    ];

    /** @return BelongsTo<ProcedureTemplateVersion, $this> */
    public function version(): BelongsTo {
        return $this->belongsTo(ProcedureTemplateVersion::class, 'procedure_template_version_id');
    }
}
