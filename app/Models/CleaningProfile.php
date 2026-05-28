<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CleaningProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\CleaningProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $label
 * @property int|null $interval_days
 * @property array<string, mixed>|null $requirements
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CleaningProfile extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<CleaningProfileFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'label',
        'interval_days',
        'requirements',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requirements' => 'array',
        'is_active' => 'bool',
        'interval_days' => 'int',
    ];

    /** @return HasMany<Room, $this> */
    public function rooms(): HasMany {
        return $this->hasMany(Room::class);
    }
}
