<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\RentalRequestStatus;
use App\Models\{Asset, Customer, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Verleih-Anfrage aus dem Kundenportal (Feature 073, MVP-714) — zweiphasig
 * wie {@see \App\Models\AppointmentRequest}: der Kunde fragt Gerät ODER
 * Gerätegruppe für einen Zeitraum an, erst die interne Annahme erzeugt
 * Verleihakte (Entwurf) und Vormerkung über die bestehenden Schreibstellen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int|null $portal_user_id
 * @property int|null $asset_id
 * @property string|null $group_code
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $note
 * @property RentalRequestStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decline_reason
 * @property int|null $rental_reservation_id
 * @property int|null $rental_case_id
 */
class RentalRequest extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'customer_id', 'portal_user_id', 'asset_id', 'group_code',
        'starts_at', 'ends_at', 'note', 'status', 'decided_by', 'decided_at',
        'decline_reason', 'rental_reservation_id', 'rental_case_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => RentalRequestStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->where('status', RentalRequestStatus::Requested->value);
    }

    public function isOpen(): bool {
        return $this->status->isOpen();
    }

    /** Anzeigename des Wunsches: Gerät oder Gerätegruppe. */
    public function subjectLabel(): string {
        if ($this->asset !== null) {
            return (string) $this->asset->name;
        }

        return $this->group_code !== null
            ? (string) __('Gerätegruppe :group', ['group' => $this->group_code])
            : '—';
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function portalUser(): BelongsTo {
        return $this->belongsTo(User::class, 'portal_user_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<RentalReservation, $this> */
    public function reservation(): BelongsTo {
        return $this->belongsTo(RentalReservation::class, 'rental_reservation_id');
    }

    /** @return BelongsTo<RentalCase, $this> */
    public function rentalCase(): BelongsTo {
        return $this->belongsTo(RentalCase::class);
    }
}
