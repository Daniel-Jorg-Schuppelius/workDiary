<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Building.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $site_id
 * @property string $name
 * @property string|null $code
 * @property string|null $gross_area_m2
 * @property int|null $year_built
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Building extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<BuildingFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'site_id',
        'name',
        'code',
        'gross_area_m2',
        'year_built',
        'notes',
        'created_by',
        'updated_by',
    ];

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<Floor, $this> */
    public function floors(): HasMany {
        return $this->hasMany(Floor::class)->orderBy('level');
    }
}
