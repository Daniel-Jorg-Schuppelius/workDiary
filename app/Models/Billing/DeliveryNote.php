<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\DeliveryNoteStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Contracts\AuditsChanges;
use App\Models\Customer\Customer;
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Sales\OrderConfirmation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Lieferschein (Phase 145, MVP-1163): eigener Beleg mit Nummernkreis und
 * Positionen ohne Preise. Vorgänger ist die Auftragsbestätigung oder der
 * Fertigungsauftrag; Lagerauslieferungen hängen über ihre Position daran.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $customer_id
 * @property int|null $project_id
 * @property int|null $order_confirmation_id
 * @property int|null $manufacturing_order_id
 * @property string $number
 * @property DeliveryNoteStatus $status
 * @property Carbon|null $delivered_on
 * @property string|null $note
 * @property int|null $created_by
 * @property-read Customer|null $customer
 * @property-read OrderConfirmation|null $orderConfirmation
 * @property-read ManufacturingOrder|null $manufacturingOrder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryNoteItem> $items
 */
class DeliveryNote extends Model implements AuditsChanges {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<\Database\Factories\Billing\DeliveryNoteFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'customer_id', 'project_id', 'order_confirmation_id', 'manufacturing_order_id',
        'number', 'status', 'delivered_on', 'note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => DeliveryNoteStatus::class,
        'delivered_on' => 'date',
    ];

    /** @return HasMany<DeliveryNoteItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(DeliveryNoteItem::class)->orderBy('position');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<OrderConfirmation, $this> */
    public function orderConfirmation(): BelongsTo {
        return $this->belongsTo(OrderConfirmation::class);
    }

    /** @return BelongsTo<ManufacturingOrder, $this> */
    public function manufacturingOrder(): BelongsTo {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Hängt eine Lagerauslieferung daran, bucht der Lagerabgang — Storno nur dort. */
    public function hasStockDeliveries(): bool {
        return $this->items->contains(fn (DeliveryNoteItem $item): bool => $item->stock_delivery_id !== null);
    }
}
