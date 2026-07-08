<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeSummary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Boundary/Zusammenfassung über einen zusammenhängenden Kinderbereich eines
 * Knotens (Feature 054, MVP-137): benannte Klammer, additiv neben der
 * Baumstruktur. `start_index`..`end_index` beziehen sich auf die Reihenfolge
 * der Kinder des Elternknotens (Mind-Elixir-Modell). Persistenz über den
 * Whole-Map-Sync ({@see \App\Services\Ideas\IdeaMapSyncService}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $idea_map_id
 * @property int $parent_node_id
 * @property int $start_index
 * @property int $end_index
 * @property string|null $label
 * @property int|null $created_by
 */
class IdeaNodeSummary extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'idea_map_id',
        'parent_node_id',
        'start_index',
        'end_index',
        'label',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_index' => 'integer',
        'end_index' => 'integer',
    ];

    /** @return BelongsTo<IdeaMap, $this> */
    public function map(): BelongsTo {
        return $this->belongsTo(IdeaMap::class, 'idea_map_id');
    }

    /** @return BelongsTo<IdeaNode, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(IdeaNode::class, 'parent_node_id');
    }
}
