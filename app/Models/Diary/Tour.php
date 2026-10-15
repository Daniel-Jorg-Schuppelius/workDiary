<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Tour.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Diary;

use App\Enums\Tour\TourStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasJournal, HasSqid};
use App\Models\Facility\Site;
use App\Models\Fleet\Vehicle;
use App\Models\Platform\User;
use App\Models\Travel\TravelLog;
use CommonToolkit\Helper\Data\JsonHelper;
use Database\Factories\Diary\TourFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property int|null $vehicle_id
 * @property Carbon|null $tour_date
 * @property Carbon|null $planned_start_at
 * @property int|null $start_site_id
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
 * @property list<array{from: string, to: string, distance_m: int, duration_s: int}>|null $route_legs
 * @property TourStatus $status
 * @property bool $travel_billed
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

    use HasJournal;
    use HasSqid;

    /** @var class-string<\App\Models\Journal\JournalEntry> */
    protected static string $journalClass = TourEvent::class;

    protected $fillable = [
        'organization_id',
        'user_id',
        'vehicle_id',
        'tour_date',
        'planned_start_at',
        'start_site_id',
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
        'route_legs',
        'status',
        'travel_billed',
        'notes',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tour_date' => 'date',
        'planned_start_at' => 'datetime',
        'start_lat' => 'decimal:7',
        'start_lng' => 'decimal:7',
        'end_lat' => 'decimal:7',
        'end_lng' => 'decimal:7',
        'planned_distance_km' => 'decimal:2',
        'planned_duration_minutes' => 'integer',
        'route_legs' => 'array',
        'status' => TourStatus::class,
        'travel_billed' => 'boolean',
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

    /** @return HasMany<TravelLog, $this> */
    public function travelLogs(): HasMany {
        return $this->hasMany(TravelLog::class);
    }

    /** @return HasMany<TourStop, $this> */
    public function stops(): HasMany {
        return $this->hasMany(TourStop::class)->orderBy('position');
    }

    /** @return HasMany<TourProposal, $this> */
    public function proposals(): HasMany {
        return $this->hasMany(TourProposal::class)->latest('id');
    }

    /** @return BelongsTo<Site, $this> */
    public function startSite(): BelongsTo {
        return $this->belongsTo(Site::class, 'start_site_id');
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
        try {
            $decoded = JsonHelper::decode($this->route_geometry);

            return is_array($decoded) ? $decoded : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
