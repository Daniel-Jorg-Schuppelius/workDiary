<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistSectionLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optionale Zuordnung eines Todoist-Abschnitts auf einen WorkDiary-Status
 * (Feature 055, MVP-112): nur `open` oder `in_progress`. Nicht zugeordnete
 * Abschnitte verändern den WorkDiary-Status NICHT (Semantik aus 055).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $todoist_project_link_id
 * @property string $todoist_section_id
 * @property string|null $name
 * @property string $task_status
 */
class TodoistSectionLink extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'todoist_project_link_id',
        'todoist_section_id',
        'name',
        'task_status',
    ];

    /** @return BelongsTo<TodoistProjectLink, $this> */
    public function projectLink(): BelongsTo {
        return $this->belongsTo(TodoistProjectLink::class, 'todoist_project_link_id');
    }
}
