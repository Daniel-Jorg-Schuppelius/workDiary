<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ShiftTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftType extends Model {
    /** @use HasFactory<ShiftTypeFactory> */
    use HasFactory;
    use Auditable;

    protected $fillable = [
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
     * Returns a DaisyUI-compatible inline style fragment for the badge background.
     */
    public function badgeStyle(): string {
        return "background-color:{$this->color};color:#fff;";
    }

    /**
     * Active types only.
     *
     * @param Builder<ShiftType> $query
     * @return Builder<ShiftType>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }
}
