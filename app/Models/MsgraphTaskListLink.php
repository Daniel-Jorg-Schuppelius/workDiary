<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphTaskListLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use App\Plugins\Support\TaskSync\TaskSyncLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zuordnung einer Microsoft-To-Do-Liste zu einem WorkDiary-Projekt bzw. zum
 * globalen Kanban (Feature 102, Schnitt E — Todoist-Muster
 * {@see TodoistProjectLink}): nur AUSDRÜCKLICH zugeordnete Listen werden
 * synchronisiert; die Richtung steuert `sync_mode`.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $todo_list_id
 * @property string|null $todo_list_name
 * @property string $target_kind
 * @property int|null $project_id
 * @property string $sync_mode
 * @property string $status
 * @property string|null $delta_link
 * @property string|null $subscription_id
 * @property Carbon|null $subscription_expires_at
 * @property string|null $webhook_secret
 * @property Carbon|null $last_run_at
 * @property array<string, int>|null $last_run_counters
 */
class MsgraphTaskListLink extends Model implements TaskSyncLink {
    use Auditable;
    use BelongsToOrganization;

    public const KIND_PROJECT = 'project';

    public const KIND_GLOBAL_KANBAN = 'global_kanban';

    public const MODE_TODO_TO_WORKDIARY = 'todo_to_workdiary';

    public const MODE_WORKDIARY_TO_TODO = 'workdiary_to_todo';

    public const MODE_BIDIRECTIONAL = 'bidirectional';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    protected $table = 'msgraph_task_list_links';

    protected $fillable = [
        'organization_id',
        'todo_list_id',
        'todo_list_name',
        'target_kind',
        'project_id',
        'sync_mode',
        'status',
        'last_run_at',
        'last_run_counters',
    ];

    /** @var list<string> */
    protected $hidden = [
        'webhook_secret',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_run_at' => 'datetime',
        'last_run_counters' => 'array',
        'subscription_expires_at' => 'datetime',
        'webhook_secret' => 'encrypted',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** {@inheritDoc} */
    public function organizationId(): int {
        return (int) $this->organization_id;
    }

    public function importsFromTodo(): bool {
        return in_array($this->sync_mode, [self::MODE_TODO_TO_WORKDIARY, self::MODE_BIDIRECTIONAL], true);
    }

    public function exportsToTodo(): bool {
        return in_array($this->sync_mode, [self::MODE_WORKDIARY_TO_TODO, self::MODE_BIDIRECTIONAL], true);
    }
}
