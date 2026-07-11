<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalDeposit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\RentalDepositStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kaution als eigener Finanzvorgang (D10) — getrennt vom Mietumsatz.
 * Einbehalt braucht eine Pflichtbegründung (retained_reason).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $rental_case_id
 * @property RentalDepositStatus $status
 * @property numeric-string $amount
 * @property numeric-string|null $retained_amount
 */
class RentalDeposit extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'rental_case_id', 'status', 'amount',
        'retained_amount', 'retained_reason', 'received_at', 'refunded_at',
        'recorded_by', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => RentalDepositStatus::class,
        'amount' => 'decimal:2',
        'retained_amount' => 'decimal:2',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /** @return BelongsTo<RentalCase, $this> */
    public function rentalCase(): BelongsTo {
        return $this->belongsTo(RentalCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
