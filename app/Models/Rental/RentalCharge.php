<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\{RentalChargeKind, RentalChargeStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Invoice, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Miet- oder Zusatzposition einer Verleihakte (MVP-266). Freigegebene
 * Positionen gehen an die Faktura: lokal als Invoice (invoice_id) oder bei
 * externer Beleghoheit mit externer Belegnummer (external_reference).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $rental_case_id
 * @property RentalChargeKind $kind
 * @property RentalChargeStatus $status
 * @property string $label
 * @property numeric-string $quantity
 * @property numeric-string $unit_price
 * @property numeric-string $amount
 */
class RentalCharge extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'rental_case_id', 'kind', 'status', 'label',
        'quantity', 'unit', 'unit_price', 'amount', 'reason_text',
        'created_by', 'released_by', 'released_at', 'invoice_id',
        'external_reference', 'invoiced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RentalChargeKind::class,
        'status' => RentalChargeStatus::class,
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'released_at' => 'datetime',
        'invoiced_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeReleased(Builder $query): void {
        $query->where('status', RentalChargeStatus::Released->value);
    }

    /** @return BelongsTo<RentalCase, $this> */
    public function rentalCase(): BelongsTo {
        return $this->belongsTo(RentalCase::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo {
        return $this->belongsTo(User::class, 'released_by');
    }
}
