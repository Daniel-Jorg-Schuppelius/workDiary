<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSprint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Agile;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Sprint (Feature 064, MVP-142). Lebenszyklus planned → active →
 * completed|cancelled; KEIN Wiederöffnen (Korrektur = Folgeaktion).
 * Snapshots sind nach dem Schreiben unveränderlich (Kennzahlen-Quelle).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $board_id
 * @property string $name
 * @property string|null $goal
 * @property \Illuminate\Support\Carbon|null $starts_on
 * @property \Illuminate\Support\Carbon|null $ends_on
 * @property string $status
 * @property array<int, array<string, mixed>>|null $commitment_snapshot
 * @property array<string, mixed>|null $completion_snapshot
 * @property array<string, mixed>|null $capacity_snapshot
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int $lock_version
 */
class AgileSprint extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_PLANNED = 'planned';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'organization_id',
        'board_id',
        'name',
        'goal',
        'starts_on',
        'ends_on',
        'status',
        'commitment_snapshot',
        'completion_snapshot',
        'capacity_snapshot',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'commitment_snapshot' => 'array',
        'completion_snapshot' => 'array',
        'capacity_snapshot' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    /** @return BelongsTo<AgileBoard, $this> */
    public function board(): BelongsTo {
        return $this->belongsTo(AgileBoard::class, 'board_id');
    }

    /** @return HasMany<AgileSprintItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(AgileSprintItem::class, 'sprint_id')->orderBy('position');
    }

    public function isActive(): bool {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFinished(): bool {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }
}
