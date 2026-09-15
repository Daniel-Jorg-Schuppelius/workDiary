<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kurskategorie (Feature 149, MVP-788): ordnet den Katalog — intern, in
 * „Meine Schulungen" und im Portal. Gepflegt als Liste in den Einstellungen
 * der Lernplattform; Export/Import tragen sie nach Name.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property int $position
 */
class LearningCourseCategory extends Model {
    use Auditable;

    use BelongsToOrganization;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /** @return HasMany<LearningCourse, $this> */
    public function courses(): HasMany {
        return $this->hasMany(LearningCourse::class, 'category_id');
    }
}
