<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileWorkItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Agile;

use App\Enums\Agile\AgileItemType;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agiles Arbeitselement (Feature 064): Beistelltabelle 1:1 zum Task —
 * Boardzustand (Spalte, Rang, Punkte, Blockierung) lebt HIER, nie am Task.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $board_id
 * @property int $task_id
 * @property AgileItemType $item_type
 * @property int|null $column_id
 * @property int $backlog_rank
 * @property int|null $story_points
 * @property \Illuminate\Support\Carbon|null $blocked_at
 * @property string|null $blocked_reason
 * @property int $lock_version
 */
class AgileWorkItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'board_id',
        'task_id',
        'item_type',
        'column_id',
        'backlog_rank',
        'story_points',
        'blocked_at',
        'blocked_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'item_type' => AgileItemType::class,
        'blocked_at' => 'datetime',
        'lock_version' => 'integer',
    ];

    /** @return BelongsTo<AgileBoard, $this> */
    public function board(): BelongsTo {
        return $this->belongsTo(AgileBoard::class, 'board_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<AgileBoardColumn, $this> */
    public function column(): BelongsTo {
        return $this->belongsTo(AgileBoardColumn::class, 'column_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<AgileSprintItem, $this> */
    public function sprintItems(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(AgileSprintItem::class, 'work_item_id');
    }

    public function isBlocked(): bool {
        return $this->blocked_at !== null;
    }
}
