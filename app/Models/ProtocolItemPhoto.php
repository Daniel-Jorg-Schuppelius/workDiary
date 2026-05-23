<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemPhoto.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Protocol\ProtocolItemPhotoPhase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot zwischen `protocol_items` und `attachments` mit fachlicher Phase
 * (Vorher/Nachher/Detail/Defekt/Referenz) (MVP-023 §2.1).
 *
 * @property int $id
 * @property int $protocol_item_id
 * @property int $attachment_id
 * @property ProtocolItemPhotoPhase $phase
 * @property string|null $caption
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $taken_at
 * @property string|null $geo_lat
 * @property string|null $geo_lng
 * @property int|null $captured_by_user_id
 */
class ProtocolItemPhoto extends Model {
    protected $fillable = [
        'protocol_item_id',
        'attachment_id',
        'phase',
        'caption',
        'sort_order',
        'taken_at',
        'geo_lat',
        'geo_lng',
        'captured_by_user_id',
    ];

    protected $casts = [
        'phase' => ProtocolItemPhotoPhase::class,
        'taken_at' => 'datetime',
        'sort_order' => 'int',
    ];

    /** @return BelongsTo<ProtocolItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(ProtocolItem::class, 'protocol_item_id');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function attachment(): BelongsTo {
        return $this->belongsTo(Attachment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function capturedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }
}
