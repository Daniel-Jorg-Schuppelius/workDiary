<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Benannte Querverbindung zwischen zwei Knoten einer Ideenlandkarte
 * (Feature 054, MVP-137): additive, gerichtete Kante neben der primären
 * Elternbeziehung. Nicht Teil der Baumnavigation — rein visueller/fachlicher
 * Bezug (z. B. „hängt ab von", „widerspricht"). Persistenz über den
 * Whole-Map-Sync ({@see \App\Services\Ideas\IdeaMapSyncService}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $idea_map_id
 * @property int $source_node_id
 * @property int $target_node_id
 * @property string|null $label
 * @property string $color
 * @property int|null $created_by
 */
class IdeaNodeLink extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'idea_map_id',
        'source_node_id',
        'target_node_id',
        'label',
        'color',
        'created_by',
    ];

    /** @return BelongsTo<IdeaMap, $this> */
    public function map(): BelongsTo {
        return $this->belongsTo(IdeaMap::class, 'idea_map_id');
    }

    /** @return BelongsTo<IdeaNode, $this> */
    public function source(): BelongsTo {
        return $this->belongsTo(IdeaNode::class, 'source_node_id');
    }

    /** @return BelongsTo<IdeaNode, $this> */
    public function target(): BelongsTo {
        return $this->belongsTo(IdeaNode::class, 'target_node_id');
    }
}
