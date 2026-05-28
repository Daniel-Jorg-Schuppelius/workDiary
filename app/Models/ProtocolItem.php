<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Protocol\{ProtocolItemResult, ProtocolItemType};
use App\Models\Concerns\{HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * @property int $id
 * @property int $protocol_id
 * @property int|null $parent_item_id
 * @property int $sort_order
 * @property string $label
 * @property string|null $description
 * @property bool $required
 * @property array<string, mixed>|null $value_json
 * @property ProtocolItemType $item_type
 * @property ProtocolItemResult|null $result
 * @property string|null $note
 * @property \Illuminate\Support\Carbon|null $measured_at
 * @property int|null $measured_by_user_id
 */
class ProtocolItem extends Model {
    use HasAttachments;

    use HasSqid;

    protected $fillable = [
        'protocol_id',
        'parent_item_id',
        'sort_order',
        'item_type',
        'label',
        'description',
        'required',
        'value_json',
        'result',
        'note',
        'measured_at',
        'measured_by_user_id',
    ];

    protected $casts = [
        'required' => 'bool',
        'value_json' => 'array',
        'item_type' => ProtocolItemType::class,
        'result' => ProtocolItemResult::class,
        'measured_at' => 'datetime',
    ];

    /** @return BelongsTo<Protocol, $this> */
    public function protocol(): BelongsTo {
        return $this->belongsTo(Protocol::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(self::class, 'parent_item_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany {
        return $this->hasMany(self::class, 'parent_item_id')->orderBy('sort_order');
    }

    /** @return HasMany<ProtocolItemPhoto, $this> */
    public function photos(): HasMany {
        return $this->hasMany(ProtocolItemPhoto::class)->orderBy('sort_order');
    }
}
