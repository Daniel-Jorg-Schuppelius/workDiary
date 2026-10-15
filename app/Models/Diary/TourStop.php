<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourStop.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Diary;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Database\Factories\Diary\TourStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Stopp einer Tour (MVP-1174): Reihenfolge, geplante Ankunft und Abfahrt aus
 * dem Vorschlag, Wartezeit sowie das Ist aus Fahreransicht oder
 * Standorterfassung. `diary_entries.tour_position` bleibt abgeleitet.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $tour_id
 * @property int $diary_entry_id
 * @property int $position
 * @property Carbon|null $planned_arrival_at
 * @property Carbon|null $planned_departure_at
 * @property int $waiting_minutes
 * @property Carbon|null $arrived_at
 * @property Carbon|null $departed_at
 * @property string|null $actual_source
 * @property string|null $outcome
 */
class TourStop extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<TourStopFactory> */
    use HasFactory;

    use HasSqid;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_GEOFENCE = 'geofence';

    public const OUTCOME_DONE = 'done';

    public const OUTCOME_NOT_MET = 'not_met';

    protected $fillable = [
        'organization_id',
        'tour_id',
        'diary_entry_id',
        'position',
        'planned_arrival_at',
        'planned_departure_at',
        'waiting_minutes',
        'arrived_at',
        'departed_at',
        'actual_source',
        'outcome',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'planned_arrival_at' => 'datetime',
        'planned_departure_at' => 'datetime',
        'waiting_minutes' => 'integer',
        'arrived_at' => 'datetime',
        'departed_at' => 'datetime',
    ];

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }
}
