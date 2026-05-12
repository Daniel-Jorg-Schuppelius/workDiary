<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\QualificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Qualification extends Model {
    /** @use HasFactory<QualificationFactory> */
    use HasFactory;
    use BelongsToOrganization;
    use Auditable;

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
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class, 'user_qualifications')
            ->withPivot(['valid_from', 'valid_until'])
            ->withTimestamps();
    }

    /**
     * Schichttypen, die diese Qualifikation voraussetzen.
     * @return BelongsToMany<ShiftType, $this>
     */
    public function shiftTypes(): BelongsToMany {
        return $this->belongsToMany(ShiftType::class, 'shift_type_qualifications');
    }

    /**
     * @param Builder<Qualification> $query
     * @return Builder<Qualification>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }
}
