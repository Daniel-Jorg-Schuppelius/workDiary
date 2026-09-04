<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Casts\MoneyCast;
use App\Enums\Reselling\PeriodStatus;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Erwartete Abrechnungsperiode eines Abos (Feature 152). Eindeutig je
 * (Abo, Beginn); der Status hält die Entscheidung — offen, berechnet,
 * teilweise, verzichtet, strittig — und überlebt jede Neuplanung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $quantity
 * @property Money|null $expected_purchase
 * @property Money|null $expected_sale
 * @property CurrencyCode $currency
 * @property PeriodStatus $status
 * @property string|null $waived_reason
 * @property string|null $note
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResalePeriodLink> $links
 * @property int|null $decided_by_user_id
 * @property CarbonImmutable|null $decided_at
 * @property-read ResaleSubscription $subscription
 */
class ResalePeriod extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'resale_periods';

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'starts_on',
        'ends_on',
        'quantity',
        'expected_purchase',
        'expected_sale',
        'currency',
        'status',
        'waived_reason',
        'note',
        'decided_by_user_id',
        'decided_at',
    ];

    protected $casts = [
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'quantity' => 'integer',
        'currency' => CurrencyCode::class,
        'expected_purchase' => MoneyCast::class . ':currency,2',
        'expected_sale' => MoneyCast::class . ':currency,2',
        'status' => PeriodStatus::class,
        'decided_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ResaleSubscription, $this> */
    public function subscription(): BelongsTo {
        return $this->belongsTo(ResaleSubscription::class, 'subscription_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** @return HasMany<ResalePeriodLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(ResalePeriodLink::class, 'period_id')->orderBy('voucher_date');
    }

    /** Länge der Periode in Monaten (Intervall des Abos: 12 oder 1). */
    public function termMonths(): int {
        $months = (int) round($this->starts_on->diffInMonths($this->ends_on->addDay()));

        return max(1, $months);
    }

    /** Benötigte Lizenzmonate: Menge × Periodenlänge. */
    public function requiredMonths(): float {
        return (float) ($this->quantity * $this->termMonths());
    }

    /** Durch Bezüge gedeckte Lizenzmonate (Vorschläge eingeschlossen). */
    public function coveredMonths(): float {
        return (float) $this->links->sum(static fn(ResalePeriodLink $l): float => (float) $l->months);
    }

    /** Nur Vorschläge, noch nichts bestätigt oder von Hand gesetzt. */
    public function isProposedOnly(): bool {
        return $this->links->isNotEmpty() && $this->links->every(static fn(ResalePeriodLink $l): bool => ! $l->origin->isDecided());
    }

    /**
     * Offene Perioden, deren Beginn erreicht ist — nur die können fehlen.
     *
     * @param  Builder<ResalePeriod>  $query
     * @return Builder<ResalePeriod>
     */
    public function scopeDue(Builder $query, CarbonImmutable $reference): Builder {
        return $query->where('status', PeriodStatus::Open->value)->where('starts_on', '<', $reference->addDay()->toDateString());
    }

    public function label(): string {
        return $this->starts_on->format('d.m.Y') . ' – ' . $this->ends_on->format('d.m.Y');
    }
}
