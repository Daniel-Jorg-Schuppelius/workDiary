<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAllocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Time;

use App\Models\Classification\ActivityCategory;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Project\{Project, Task};
use App\Support\MorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use App\Models\Asset\Asset;
use App\Models\Finance\CostCenter;
use App\Models\Facility\Site;
use App\Models\Time\TimeDimensionValue;
use App\Models\Time\TimeEntry;
use App\Models\Fleet\Vehicle;

/**
 * MVP-514 (Feature 103): Anteil eines Zeiteintrags auf einer fachlichen
 * Dimension — referenziert BESTEHENDE Modelle polymorph (keine
 * Spiegelstammdaten). Die Summe der Anteile eines Eintrags darf dessen
 * Dauer nicht übersteigen; einzige Schreibstelle ist der
 * {@see \App\Services\Timekeeping\TimeAllocationService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $time_entry_id
 * @property string $allocatable_type
 * @property int $allocatable_id
 * @property int $duration_minutes
 * @property string|null $quantity
 * @property string|null $comment
 */
class TimeAllocation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /**
     * Erlaubte Dimensionstypen: UI-Alias → Modellklasse. Aufgaben und
     * Assets sind modellseitig erlaubt, im Dialog v1 aber nicht angeboten
     * (Listen unbegrenzt groß — Picker folgt bei Bedarf).
     *
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'project' => Project::class,
        'task' => Task::class,
        'cost_center' => CostCenter::class,
        'site' => Site::class,
        'asset' => Asset::class,
        'vehicle' => Vehicle::class,
        'activity_category' => ActivityCategory::class,
        // P2: frei definierbare Mandanten-Dimension (Wert-Ebene).
        'dimension' => TimeDimensionValue::class,
    ];

    protected $table = 'time_allocations';

    protected $fillable = [
        'organization_id',
        'time_entry_id',
        'allocatable_type',
        'allocatable_id',
        'duration_minutes',
        'quantity',
        'comment',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'quantity' => 'decimal:2',
    ];

    /** @return BelongsTo<TimeEntry, $this> */
    public function timeEntry(): BelongsTo {
        return $this->belongsTo(TimeEntry::class);
    }

    /** @return MorphTo<Model, $this> */
    public function allocatable(): MorphTo {
        return $this->morphTo();
    }

    /** UI-Alias der Zielklasse (Umkehrung von TYPES; unbekannt → null). */
    public function typeAlias(): ?string {
        $alias = array_search(MorphMap::classFor($this->allocatable_type) ?? $this->allocatable_type, self::TYPES, true);

        return $alias === false ? null : $alias;
    }
}
