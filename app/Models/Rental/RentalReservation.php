<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalReservation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Enums\Rental\RentalReservationKind;
use App\Models\Asset;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Belegungsfenster im Verfügbarkeitskalender (MVP-260): Reservierung,
 * Verleihzeitraum, Wartungs-/Reinigungs-/Transportfenster. Pufferzeiten
 * erweitern den blockierten Zeitraum um Transport/Rüsten/Reinigung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $rental_case_id
 * @property int $asset_id
 * @property RentalReservationKind $kind
 * @property string $status
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property int $buffer_before_hours
 * @property int $buffer_after_hours
 */
class RentalReservation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['active', 'completed', 'cancelled'];

    protected $fillable = [
        'organization_id', 'rental_case_id', 'asset_id', 'kind', 'status',
        'starts_at', 'ends_at', 'buffer_before_hours', 'buffer_after_hours',
        'note', 'created_by', 'cancelled_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RentalReservationKind::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'buffer_before_hours' => 'integer',
        'buffer_after_hours' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void {
        $query->where('status', 'active');
    }

    /**
     * Überlappung inklusive Pufferzeiten. Da Puffer je Zeile variieren,
     * wird großzügig vorgefiltert und in PHP exakt geprüft.
     *
     * @param Builder<self> $query
     */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to, int $maxBufferHours = 168): void {
        $query->where('starts_at', '<', $to->copy()->addHours($maxBufferHours))
            ->where('ends_at', '>', $from->copy()->subHours($maxBufferHours));
    }

    public function blockedFrom(): Carbon {
        return $this->starts_at->copy()->subHours($this->buffer_before_hours);
    }

    public function blockedUntil(): Carbon {
        return $this->ends_at->copy()->addHours($this->buffer_after_hours);
    }

    public function overlapsWindow(Carbon $from, Carbon $to): bool {
        return $this->blockedFrom() < $to && $this->blockedUntil() > $from;
    }

    /** @return BelongsTo<RentalCase, $this> */
    public function rentalCase(): BelongsTo {
        return $this->belongsTo(RentalCase::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }
}
