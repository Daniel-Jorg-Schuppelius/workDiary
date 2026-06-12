<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsControl.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\ControlImplementationStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsControlFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

/**
 * NORMNEUTRALE Maßnahme (Feature 046, gemeinsamer Managementsystem-Kern;
 * vormals Control mit SoA-Feldern aus Feature 044 MVP 1): betriebliche
 * Maßnahme mit Umsetzungsstatus, Nachweis-Notiz und Owner. Eine Maßnahme
 * erfüllt n:m Normanforderungen ({@see IsmsRequirement}, Pivot
 * isms_control_requirement) und behandelt n:m Risiken ({@see IsmsRisk},
 * Pivot isms_control_risk). Die SoA-Aussage (anwendbar/Begründung) liegt
 * NICHT mehr hier, sondern im {@see IsmsApplicabilityStatement}.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property ControlImplementationStatus $implementation_status
 * @property string|null $evidence_note
 * @property int|null $owner_user_id
 */
class IsmsControl extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsControlFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'implementation_status',
        'evidence_note',
        'owner_user_id',
    ];

    protected $casts = [
        'implementation_status' => ControlImplementationStatus::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<IsmsRisk, $this> */
    public function risks(): BelongsToMany {
        return $this->belongsToMany(IsmsRisk::class, 'isms_control_risk', 'control_id', 'risk_id');
    }

    /** @return BelongsToMany<IsmsRequirement, $this> */
    public function requirements(): BelongsToMany {
        return $this->belongsToMany(IsmsRequirement::class, 'isms_control_requirement', 'control_id', 'requirement_id');
    }
}
