<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSprintItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Agile;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint-Zuordnung eines Arbeitselements (Feature 064, MVP-142).
 * added_after_start markiert Scope-Zugänge nach Sprint-Start.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $sprint_id
 * @property int $work_item_id
 * @property int $position
 * @property bool $added_after_start
 */
class AgileSprintItem extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'sprint_id',
        'work_item_id',
        'position',
        'added_after_start',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'added_after_start' => 'boolean',
    ];

    /** @return BelongsTo<AgileSprint, $this> */
    public function sprint(): BelongsTo {
        return $this->belongsTo(AgileSprint::class, 'sprint_id');
    }

    /** @return BelongsTo<AgileWorkItem, $this> */
    public function workItem(): BelongsTo {
        return $this->belongsTo(AgileWorkItem::class, 'work_item_id');
    }
}
