<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningPath.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lernpfad (Feature 149, MVP-745): geordnete Folge von Kursen — Onboarding,
 * Einarbeitung an einer Maschine, Aufstiegsqualifikation.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property string|null $target_role
 * @property int|null $duration_days
 * @property bool $is_active
 */
class LearningPath extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'title',
        'description',
        'target_role',
        'duration_days',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'duration_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<LearningPathItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(LearningPathItem::class)->orderBy('position');
    }
}
