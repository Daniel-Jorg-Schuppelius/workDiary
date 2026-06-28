<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqItemProgress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Gaeb\BoqProgressSource;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aufmaß-/Mengenfortschritt einer LV-Position (Feature 049, MVP-083). Additive
 * Meldungen; der Ausführungsstand ergibt sich aus der Summe.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $boq_item_id
 * @property string $quantity
 * @property BoqProgressSource $source
 * @property int|null $diary_entry_id
 * @property int|null $material_usage_id
 * @property string|null $note
 * @property \Illuminate\Support\Carbon $captured_at
 */
class BoqItemProgress extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'boq_item_progress';

    protected $fillable = [
        'organization_id',
        'boq_item_id',
        'quantity',
        'source',
        'diary_entry_id',
        'material_usage_id',
        'note',
        'captured_at',
        'created_by',
    ];

    protected $casts = [
        'source' => BoqProgressSource::class,
        'quantity' => 'decimal:4',
        'captured_at' => 'datetime',
    ];

    /** @return BelongsTo<BoqItem, $this> */
    public function item(): BelongsTo {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }
}
