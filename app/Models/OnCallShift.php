<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallShift.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasTags};
use App\Models\Contracts\HasTimeWindow;
use Carbon\Carbon;
use Database\Factories\OnCallShiftFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @property Carbon $start_at
 * @property Carbon $end_at
 */
class OnCallShift extends Model implements HasTimeWindow {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<OnCallShiftFactory> */
    use HasFactory;

    use HasTags;

    protected $fillable = [
        'organization_id',
        'legacy_id',
        'user_id',
        'start_at',
        'end_at',
        'note',
        'is_archived',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_archived' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<EmergencyAssignment, $this> */
    public function emergencyAssignments(): HasMany {
        return $this->hasMany(EmergencyAssignment::class);
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    /**
     * Shifts that overlap a given period.
     *
     * @param  Builder<OnCallShift>  $query
     * @return Builder<OnCallShift>
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }

    public function getStartAt(): ?Carbon {
        return $this->start_at;
    }

    public function getEndAt(): ?Carbon {
        return $this->end_at;
    }
}
