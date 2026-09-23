<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGradingVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Enums\Club\ClubGradingVersionStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Regelversion einer Ordnung (MVP-846): neue Prüfungsangebote nehmen die
 * aktive Version, bestehende behalten ihre. Nach Verwendung nicht löschbar.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_grading_system_id
 * @property int $version_no
 * @property ClubGradingVersionStatus $status
 * @property Carbon|null $valid_from
 * @property int|null $unit_minutes
 * @property bool $accepts_external_credits
 * @property string|null $notes
 * @property Carbon|null $activated_at
 */
class ClubGradingVersion extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_grading_system_id', 'version_no', 'status', 'valid_from', 'unit_minutes', 'accepts_external_credits', 'notes', 'activated_at'];

    protected $casts = [
        'version_no' => 'integer',
        'status' => ClubGradingVersionStatus::class,
        'valid_from' => 'date',
        'unit_minutes' => 'integer',
        'accepts_external_credits' => 'boolean',
        'activated_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubGradingSystem, $this> */
    public function system(): BelongsTo {
        return $this->belongsTo(ClubGradingSystem::class, 'club_grading_system_id');
    }

    /** @return HasMany<ClubGradeRequirement, $this> */
    public function requirements(): HasMany {
        return $this->hasMany(ClubGradeRequirement::class);
    }

    public function isActive(): bool {
        return $this->status === ClubGradingVersionStatus::Active;
    }

    public function isDraft(): bool {
        return $this->status === ClubGradingVersionStatus::Draft;
    }
}
