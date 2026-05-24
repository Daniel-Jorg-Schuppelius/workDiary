<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\ClassificationRequirementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $entry_type_code
 * @property string $required_domain
 * @property ClassificationRequirementPhase $enforce_phase
 * @property ClassificationRequirementSeverity $severity
 * @property bool $allow_multi
 * @property int $min_count
 * @property int|null $max_count
 * @property array<string, list<string>>|null $only_if_json
 * @property string|null $note
 */
class ClassificationRequirement extends Model {
    /** @use HasFactory<ClassificationRequirementFactory> */
    use Auditable, BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'entry_type_code',
        'required_domain',
        'enforce_phase',
        'severity',
        'allow_multi',
        'min_count',
        'max_count',
        'only_if_json',
        'note',
    ];

    protected $casts = [
        'enforce_phase' => ClassificationRequirementPhase::class,
        'severity' => ClassificationRequirementSeverity::class,
        'allow_multi' => 'bool',
        'min_count' => 'int',
        'max_count' => 'int',
        'only_if_json' => 'array',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }
}
