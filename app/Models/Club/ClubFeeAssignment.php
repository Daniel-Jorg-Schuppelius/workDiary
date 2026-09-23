<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zeitliche Zuordnung Mitglied → Beitragskonto und Tarif (MVP-849): je Mitglied
 * höchstens eine Zuordnung zur selben Zeit; Einzelnachlass in Prozent mit Grund.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $club_fee_account_id
 * @property int $club_member_id
 * @property int $club_fee_tariff_id
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property string|null $discount_percent
 * @property string|null $discount_reason
 * @property Carbon|null $review_required_at
 * @property string|null $review_note
 */
class ClubFeeAssignment extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'club_fee_account_id', 'club_member_id', 'club_fee_tariff_id', 'valid_from', 'valid_to', 'discount_percent', 'discount_reason', 'review_required_at', 'review_note'];

    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date', 'review_required_at' => 'datetime'];

    /** @return BelongsTo<ClubFeeAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(ClubFeeAccount::class, 'club_fee_account_id');
    }

    /** @return BelongsTo<ClubMember, $this> */
    public function member(): BelongsTo {
        return $this->belongsTo(ClubMember::class, 'club_member_id');
    }

    /** @return BelongsTo<ClubFeeTariff, $this> */
    public function tariff(): BelongsTo {
        return $this->belongsTo(ClubFeeTariff::class, 'club_fee_tariff_id');
    }

    public function isActiveOn(CarbonInterface $day): bool {
        return $this->valid_from->lessThanOrEqualTo($day) && ($this->valid_to === null || $this->valid_to->greaterThanOrEqualTo($day));
    }

    public function needsReview(): bool {
        return $this->review_required_at !== null;
    }

    /** Nachlass als Decimal-String ohne Float-Arithmetik ("0" ohne Nachlass). */
    public function discountPercent(): string {
        return $this->discount_percent !== null && $this->discount_percent !== '' ? (string) $this->discount_percent : '0';
    }

    /**
     * @param  Builder<ClubFeeAssignment>  $query
     * @return Builder<ClubFeeAssignment>
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder {
        return $query
            ->where('valid_from', '<', DateRange::dayAfter($to))
            ->where(fn(Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($from)));
    }
}
