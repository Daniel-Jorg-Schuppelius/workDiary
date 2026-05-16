<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Qualification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\QualificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $abbreviation
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Qualification extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<QualificationFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'abbreviation',
        'description',
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

    /**
     * Mitarbeiter mit dieser Qualifikation.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class, 'user_qualifications')
            ->withPivot(['valid_from', 'valid_until'])
            ->withTimestamps();
    }

    /**
     * Schichttypen, die diese Qualifikation voraussetzen.
     *
     * @return BelongsToMany<ShiftType, $this>
     */
    public function shiftTypes(): BelongsToMany {
        return $this->belongsToMany(ShiftType::class, 'shift_type_qualifications');
    }

    /**
     * @param  Builder<Qualification>  $query
     * @return Builder<Qualification>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }
}
