<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Tour.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Tour\TourStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property int|null $vehicle_id
 * @property Carbon|null $tour_date
 * @property string|null $name
 * @property string|null $start_address
 * @property string|null $start_lat
 * @property string|null $start_lng
 * @property string|null $end_address
 * @property string|null $end_lat
 * @property string|null $end_lng
 * @property string $planned_distance_km
 * @property int $planned_duration_minutes
 * @property string|null $route_geometry
 * @property TourStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tour extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<TourFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'vehicle_id',
        'tour_date',
        'name',
        'start_address',
        'start_lat',
        'start_lng',
        'end_address',
        'end_lat',
        'end_lng',
        'planned_distance_km',
        'planned_duration_minutes',
        'route_geometry',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tour_date' => 'date',
        'start_lat' => 'decimal:7',
        'start_lng' => 'decimal:7',
        'end_lat' => 'decimal:7',
        'end_lng' => 'decimal:7',
        'planned_distance_km' => 'decimal:2',
        'planned_duration_minutes' => 'integer',
        'status' => TourStatus::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function orderedStops(): HasMany {
        return $this->diaryEntries()
            ->orderByRaw('tour_position IS NULL')
            ->orderBy('tour_position')
            ->orderBy('id');
    }

    /**
     * @param  Builder<Tour>  $query
     * @return Builder<Tour>
     */
    public function scopeForUser(Builder $query, int $userId): Builder {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Tour>  $query
     * @return Builder<Tour>
     */
    public function scopeOnDate(Builder $query, string $date): Builder {
        return $query->whereDate('tour_date', $date);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function geometryArray(): ?array {
        if ($this->route_geometry === null || $this->route_geometry === '') {
            return null;
        }
        $decoded = json_decode($this->route_geometry, true);

        return is_array($decoded) ? $decoded : null;
    }
}
