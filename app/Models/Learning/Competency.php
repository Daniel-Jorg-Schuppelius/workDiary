<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Competency.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kompetenz mit Stufen (Feature 149, MVP-745).
 *
 * **Nicht zu verwechseln mit der Qualifikation** (Feature 013): die
 * Qualifikation ist ein Nachweis mit Gültigkeit und Sperrwirkung, die
 * Kompetenz eine Einschätzung ("kann anleiten"). Sie sperrt nichts.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $max_level
 * @property string|null $category
 * @property bool $is_active
 */
class Competency extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'description',
        'max_level',
        'category',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'max_level' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<UserCompetency, $this> */
    public function userCompetencies(): HasMany {
        return $this->hasMany(UserCompetency::class);
    }

    /** @return HasMany<CompetencyRequirement, $this> */
    public function requirements(): HasMany {
        return $this->hasMany(CompetencyRequirement::class);
    }

    /** Stufe auf den gültigen Bereich begrenzen. */
    public function clampLevel(int $level): int {
        return max(1, min($level, max(1, $this->max_level)));
    }
}
