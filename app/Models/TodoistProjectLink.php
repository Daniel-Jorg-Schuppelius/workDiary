<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistProjectLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Plugins\Support\TaskSync\TaskSyncLink;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Explizite Zuordnung eines Todoist-Projekts (Feature 055, MVP-112) auf ein
 * WorkDiary-Projekt oder das globale Kanban. Sync-Richtung und Datenführung
 * sind je Zuordnung sichtbar festgelegt (DoD); nur `active`-Zuordnungen
 * werden synchronisiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $todoist_project_id
 * @property string|null $todoist_project_name
 * @property string $target_kind
 * @property int|null $project_id
 * @property string $sync_mode
 * @property string $status
 * @property Carbon|null $last_run_at
 * @property array<string, int>|null $last_run_counters
 */
class TodoistProjectLink extends Model implements TaskSyncLink {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    public const KIND_PROJECT = 'project';

    public const KIND_GLOBAL_KANBAN = 'global_kanban';

    public const MODE_TODOIST_TO_WORKDIARY = 'todoist_to_workdiary';

    public const MODE_WORKDIARY_TO_TODOIST = 'workdiary_to_todoist';

    public const MODE_BIDIRECTIONAL = 'bidirectional';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'organization_id',
        'todoist_project_id',
        'todoist_project_name',
        'target_kind',
        'project_id',
        'sync_mode',
        'status',
        'last_run_at',
        'last_run_counters',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_run_at' => 'datetime',
        'last_run_counters' => 'array',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<TodoistSectionLink, $this> */
    public function sectionLinks(): HasMany {
        return $this->hasMany(TodoistSectionLink::class, 'todoist_project_link_id');
    }

    /** {@inheritDoc} */
    public function organizationId(): int {
        return (int) $this->organization_id;
    }

    /** Importrichtung aktiv (Todoist → WorkDiary)? */
    public function importsFromTodoist(): bool {
        return in_array($this->sync_mode, [self::MODE_TODOIST_TO_WORKDIARY, self::MODE_BIDIRECTIONAL], true);
    }

    /** Exportrichtung aktiv (WorkDiary → Todoist)? */
    public function exportsToTodoist(): bool {
        return in_array($this->sync_mode, [self::MODE_WORKDIARY_TO_TODOIST, self::MODE_BIDIRECTIONAL], true);
    }
}
