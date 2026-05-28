<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Floor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\FloorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $building_id
 * @property int $level
 * @property string $label
 * @property string|null $gross_area_m2
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Floor extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<FloorFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'building_id',
        'level',
        'label',
        'gross_area_m2',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'level' => 'int',
    ];

    /** @return BelongsTo<Building, $this> */
    public function building(): BelongsTo {
        return $this->belongsTo(Building::class);
    }

    /** @return HasMany<Room, $this> */
    public function rooms(): HasMany {
        return $this->hasMany(Room::class)->orderBy('name');
    }
}
