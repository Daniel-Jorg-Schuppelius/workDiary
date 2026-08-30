<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CompetencyRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soll-Kompetenz je Rolle oder Team (Feature 149, MVP-745) — dasselbe
 * Muster wie die Pflichtmatrix aus Feature 145, damit beide gleich gelesen
 * werden.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $competency_id
 * @property string $subject_kind
 * @property string $subject_key
 * @property int $required_level
 * @property bool $is_active
 */
class CompetencyRequirement extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'competency_id',
        'subject_kind',
        'subject_key',
        'required_level',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'required_level' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo {
        return $this->belongsTo(Competency::class);
    }
}
