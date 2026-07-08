<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileBoardColumn.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Agile;

use App\Enums\Agile\AgileColumnCategory;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Board-Spalte (Feature 064): Kategorie mappt auf den Task-Status,
 * optionales WIP-Limit (Übersteuerung nur mit agile.workflow.override).
 *
 * @property int $id
 * @property int $board_id
 * @property string $name
 * @property AgileColumnCategory $category
 * @property string|null $report_role
 * @property int $position
 * @property int|null $wip_limit
 */
class AgileBoardColumn extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'board_id',
        'name',
        'category',
        'report_role',
        'position',
        'wip_limit',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'category' => AgileColumnCategory::class,
    ];

    /** @return BelongsTo<AgileBoard, $this> */
    public function board(): BelongsTo {
        return $this->belongsTo(AgileBoard::class, 'board_id');
    }

    /** @return HasMany<AgileWorkItem, $this> */
    public function workItems(): HasMany {
        return $this->hasMany(AgileWorkItem::class, 'column_id');
    }
}
