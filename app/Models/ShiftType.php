<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\Carbon;
use Database\Factories\ShiftTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $abbreviation
 * @property string|null $color
 * @property string|null $default_start_time
 * @property string|null $default_end_time
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ShiftType extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ShiftTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'abbreviation',
        'color',
        'default_start_time',
        'default_end_time',
        'is_active',
        'created_by',
    ];

    protected function casts(): array {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ScheduledShift, $this> */
    public function scheduledShifts(): HasMany {
        return $this->hasMany(ScheduledShift::class);
    }

    /**
     * Pflichtqualifikationen für diesen Schichttyp.
     *
     * @return BelongsToMany<Qualification, $this>
     */
    public function qualifications(): BelongsToMany {
        return $this->belongsToMany(Qualification::class, 'shift_type_qualifications');
    }

    /**
     * Returns a DaisyUI-compatible inline style fragment for the badge background.
     */
    public function badgeStyle(): string {
        return "background-color:{$this->color};color:#fff;";
    }

    /**
     * Active types only.
     *
     * @param  Builder<ShiftType>  $query
     * @return Builder<ShiftType>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }
}
