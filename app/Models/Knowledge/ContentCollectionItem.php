<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentCollectionItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Knowledge;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;
use App\Models\Knowledge\ContentCollection;

/**
 * Zeiger einer Sammlung auf einen Inhalt (MVP-809). Derselbe Inhalt darf in
 * mehreren Sammlungen liegen; in einer Sammlung nur einmal.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $collection_id
 * @property string $collectable_type
 * @property int $collectable_id
 * @property int $position
 * @property int|null $added_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ContentCollection|null $collection
 * @property-read User|null $adder
 */
class ContentCollectionItem extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'collection_items';

    protected $fillable = [
        'organization_id',
        'collection_id',
        'collectable_type',
        'collectable_id',
        'position',
        'added_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'collectable_id' => 'integer',
        'position' => 'integer',
    ];

    /** @return BelongsTo<ContentCollection, $this> */
    public function collection(): BelongsTo {
        return $this->belongsTo(ContentCollection::class, 'collection_id');
    }

    /** @return MorphTo<Model, $this> */
    public function collectable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function adder(): BelongsTo {
        return $this->belongsTo(User::class, 'added_by');
    }
}
