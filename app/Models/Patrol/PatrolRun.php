<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Patrol;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Durchführung eines Rundgangs (Feature 089): Start, Scans, Abschluss —
 * mit Begründungspflicht bei Abweichungen.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $patrol_route_id
 * @property int|null $started_by
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string|null $deviation_note
 */
class PatrolRun extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABORTED = 'aborted';

    protected $fillable = [
        'organization_id', 'patrol_route_id', 'started_by', 'status',
        'started_at', 'finished_at', 'deviation_note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /** @return BelongsTo<PatrolRoute, $this> */
    public function route(): BelongsTo {
        return $this->belongsTo(PatrolRoute::class, 'patrol_route_id');
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** @return HasMany<PatrolScan, $this> */
    public function scans(): HasMany {
        return $this->hasMany(PatrolScan::class);
    }
}
