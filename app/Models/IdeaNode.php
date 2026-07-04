<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaNode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Ideas\IdeaNodeColor;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Knoten einer Ideenlandkarte (Feature 054, MVP-105): hierarchischer Zweig
 * mit Position (Canvas), Sortierung (Gliederung), optimistischer Sperre
 * (`lock_version`, P4) und wiederherstellbarem Löschen. Sichtbarkeit besitzt
 * ein Knoten NIE selbst — jeder Zugriff delegiert an die Karten-Policy.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $idea_map_id
 * @property int|null $parent_id
 * @property bool $is_root
 * @property string $title
 * @property string|null $note
 * @property IdeaNodeColor $color
 * @property string|null $node_status
 * @property int|null $pos_x
 * @property int|null $pos_y
 * @property int $sort_order
 * @property int $lock_version
 */
class IdeaNode extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'idea_map_id',
        'parent_id',
        'is_root',
        'title',
        'note',
        'color',
        'node_status',
        'pos_x',
        'pos_y',
        'sort_order',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_root' => 'boolean',
        'color' => IdeaNodeColor::class,
        'pos_x' => 'integer',
        'pos_y' => 'integer',
        'sort_order' => 'integer',
        'lock_version' => 'integer',
    ];

    /** @return BelongsTo<IdeaMap, $this> */
    public function map(): BelongsTo {
        return $this->belongsTo(IdeaMap::class, 'idea_map_id');
    }

    /** @return BelongsTo<IdeaNode, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<IdeaNode, $this> */
    public function children(): HasMany {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<IdeaNodeReference, $this> */
    public function references(): HasMany {
        return $this->hasMany(IdeaNodeReference::class);
    }
}
