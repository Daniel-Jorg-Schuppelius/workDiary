<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileBoard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Agile;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Projektboard (Feature 064, MVP-139): genau ein Board je Projekt (MVP),
 * Methode Kanban oder Scrum, geordnete DoD-Liste, optimistische Sperre
 * (lock_version — Konfliktschutz nach IdeaMap-Muster, nie Last-write-wins).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $project_id
 * @property string $method
 * @property string $name
 * @property string|null $description
 * @property array<int, string>|null $dod_items
 * @property int $lock_version
 */
class AgileBoard extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const METHOD_KANBAN = 'kanban';

    public const METHOD_SCRUM = 'scrum';

    protected $fillable = [
        'organization_id',
        'project_id',
        'method',
        'name',
        'description',
        'dod_items',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'dod_items' => 'array',
        'lock_version' => 'integer',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<AgileBoardColumn, $this> */
    public function columns(): HasMany {
        return $this->hasMany(AgileBoardColumn::class, 'board_id')->orderBy('position');
    }

    /** @return HasMany<AgileWorkItem, $this> */
    public function workItems(): HasMany {
        return $this->hasMany(AgileWorkItem::class, 'board_id')->orderBy('backlog_rank');
    }
}
