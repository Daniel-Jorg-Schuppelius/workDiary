<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubGrade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Grad einer Ordnung (MVP-846): Name und Rang sind Fachdaten, Farbe nur Darstellung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_grading_system_id
 * @property string $name
 * @property int $rank
 * @property string|null $color
 * @property bool $is_active
 */
class ClubGrade extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_grading_system_id', 'name', 'rank', 'color', 'is_active'];

    protected $casts = ['rank' => 'integer', 'is_active' => 'boolean'];

    /** @return BelongsTo<ClubGradingSystem, $this> */
    public function system(): BelongsTo {
        return $this->belongsTo(ClubGradingSystem::class, 'club_grading_system_id');
    }

    /** @return HasMany<ClubMemberGrade, $this> */
    public function memberGrades(): HasMany {
        return $this->hasMany(ClubMemberGrade::class);
    }
}
