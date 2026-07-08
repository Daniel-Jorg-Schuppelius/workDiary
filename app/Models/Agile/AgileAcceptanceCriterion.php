<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileAcceptanceCriterion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Agile;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Akzeptanzkriterium eines Arbeitselements (Feature 064, MVP-140).
 *
 * @property int $id
 * @property int $work_item_id
 * @property int $position
 * @property string $text
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property int|null $checked_by
 */
class AgileAcceptanceCriterion extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'agile_acceptance_criteria';

    protected $fillable = [
        'organization_id',
        'work_item_id',
        'position',
        'text',
        'checked_at',
        'checked_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'checked_at' => 'datetime',
    ];

    /** @return BelongsTo<AgileWorkItem, $this> */
    public function workItem(): BelongsTo {
        return $this->belongsTo(AgileWorkItem::class, 'work_item_id');
    }
}
