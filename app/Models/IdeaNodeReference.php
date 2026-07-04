<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNodeReference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Referenz eines Ideen-Knotens auf ein Zielobjekt (Feature 054, MVP-109):
 * `converted` = aus dem Knoten überführt (Task/Projekt/Wissensartikel,
 * idempotent je Typ), `linked` = Verweis auf bestehenden Kunden/Projekt/
 * Auftrag. Ziel-Typen sind auf eine Whitelist begrenzt (Muster
 * {@see KnowledgeArticleLink}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $idea_node_id
 * @property string $target_type
 * @property int $target_id
 * @property string $kind
 * @property int|null $created_by
 */
class IdeaNodeReference extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const KIND_CONVERTED = 'converted';

    public const KIND_LINKED = 'linked';

    protected $fillable = [
        'organization_id',
        'idea_node_id',
        'target_type',
        'target_id',
        'kind',
        'created_by',
    ];

    /** @return BelongsTo<IdeaNode, $this> */
    public function node(): BelongsTo {
        return $this->belongsTo(IdeaNode::class, 'idea_node_id');
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo {
        return $this->morphTo(__FUNCTION__, 'target_type', 'target_id');
    }
}
