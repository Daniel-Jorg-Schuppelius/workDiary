<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DesiredShift.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Shift\ShiftPreference;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use Database\Factories\DesiredShiftFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wunsch/Abneigung eines Mitarbeiters für eine konkrete Schicht (Feature 007).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property Carbon $date
 * @property int|null $shift_type_id
 * @property ShiftPreference $preference
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DesiredShift extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<DesiredShiftFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'date',
        'shift_type_id',
        'preference',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'preference' => ShiftPreference::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ShiftType, $this> */
    public function shiftType(): BelongsTo {
        return $this->belongsTo(ShiftType::class);
    }

    /**
     * @param  Builder<DesiredShift>  $query
     * @return Builder<DesiredShift>
     */
    public function scopeForDate(Builder $query, \DateTimeInterface|string $date): Builder {
        return $query->whereDate('date', $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date);
    }

    /**
     * @param  Builder<DesiredShift>  $query
     * @return Builder<DesiredShift>
     */
    public function scopeForUser(Builder $query, int $userId): Builder {
        return $query->where('user_id', $userId);
    }
}
