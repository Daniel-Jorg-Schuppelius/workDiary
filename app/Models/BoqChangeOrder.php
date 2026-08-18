<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqChangeOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Gaeb\{BoqChangeOrderInitiator, BoqChangeOrderPhase, BoqChangeOrderStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachtragskopf eines Leistungsverzeichnisses (GAEB `COInfo`). Ein LV kann
 * mehrere tragen; die Positionen hängen über ihre Nachtragsnummer daran.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bill_of_quantity_id
 * @property string $number
 * @property BoqChangeOrderPhase|null $phase
 * @property BoqChangeOrderStatus|null $status
 * @property BoqChangeOrderInitiator|null $initiator
 * @property string|null $reason
 * @property string|null $contract_reference
 * @property \Illuminate\Support\Carbon|null $date
 */
class BoqChangeOrder extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'bill_of_quantity_id',
        'number',
        'phase',
        'status',
        'initiator',
        'reason',
        'contract_reference',
        'date',
    ];

    protected $casts = [
        'phase' => BoqChangeOrderPhase::class,
        'status' => BoqChangeOrderStatus::class,
        'initiator' => BoqChangeOrderInitiator::class,
        'date' => 'date',
    ];

    /** @return BelongsTo<BillOfQuantity, $this> */
    public function billOfQuantity(): BelongsTo {
        return $this->belongsTo(BillOfQuantity::class);
    }

    /**
     * Positionen dieses Nachtrags. Bewusst keine Eloquent-Relation: die
     * Verknüpfung läuft über LV *und* Nachtragsnummer, und ein `hasMany` mit
     * nachgeschobener Bedingung würde beim Eager Loading die Nummer des ersten
     * Modells auf alle anwenden.
     *
     * @return Builder<BoqItem>
     */
    public function items(): Builder {
        return BoqItem::query()
            ->where('bill_of_quantity_id', $this->bill_of_quantity_id)
            ->where('change_order_no', $this->number);
    }

    /**
     * Abgeschlossen — weder offen noch verhandelbar. Ein abweichender Status an
     * der Position hat laut GAEB Vorrang vor diesem hier.
     */
    public function isFinal(): bool {
        return $this->status?->isFinal() ?? false;
    }
}
