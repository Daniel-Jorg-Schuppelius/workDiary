<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeClaim.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Casts\MoneyCast;
use App\Enums\Club\ClubFeeClaimStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, DocumentDispatch, User};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Beitragsforderung (MVP-850): führende Quelle je Konto und Lauf — Mitteilung,
 * offener Posten, Zahlung und Einzug verweisen auf sie. Freigegebene Beträge
 * werden nie überschrieben; Korrekturen sind eigene, verknüpfte Forderungen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $club_fee_run_id
 * @property int $club_fee_account_id
 * @property int $customer_id
 * @property int $sequence
 * @property string $number
 * @property string $kind
 * @property int|null $corrects_claim_id
 * @property ClubFeeClaimStatus $status
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $issued_on
 * @property Carbon $due_on
 * @property Money $total
 * @property Money $paid_amount
 * @property CurrencyCode $currency
 * @property array<string, mixed>|null $payer_snapshot
 * @property string|null $reason
 * @property string|null $notes
 * @property int $dunning_level
 * @property Carbon|null $dunned_at
 * @property Carbon|null $dunning_blocked_at
 * @property string|null $dunning_block_reason
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 * @property int|null $payment_run_item_id
 * @property int $collection_attempts
 * @property Carbon|null $collection_blocked_at
 * @property string|null $collection_block_reason
 */
class ClubFeeClaim extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    public const KIND_CLAIM = 'claim';

    public const KIND_CORRECTION = 'correction';

    public const DOCUMENT_KIND = 'fee_notice';

    protected $fillable = [
        'organization_id', 'club_fee_run_id', 'club_fee_account_id', 'customer_id', 'sequence', 'number', 'kind', 'corrects_claim_id',
        'status', 'period_start', 'period_end', 'issued_on', 'due_on', 'total', 'paid_amount', 'currency', 'payer_snapshot', 'reason', 'notes',
        'dunning_level', 'dunned_at', 'dunning_blocked_at', 'dunning_block_reason', 'paid_at', 'cancelled_at', 'cancelled_by_user_id',
        'payment_run_item_id', 'collection_attempts', 'collection_blocked_at', 'collection_block_reason',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'status' => ClubFeeClaimStatus::class,
        'period_start' => 'date',
        'period_end' => 'date',
        'issued_on' => 'date',
        'due_on' => 'date',
        'total' => MoneyCast::class . ':currency',
        'paid_amount' => MoneyCast::class . ':currency',
        'currency' => CurrencyCode::class,
        'payer_snapshot' => 'array',
        'dunning_level' => 'integer',
        'dunned_at' => 'datetime',
        'dunning_blocked_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'collection_attempts' => 'integer',
        'collection_blocked_at' => 'datetime',
    ];

    /** @return BelongsTo<ClubFeeRun, $this> */
    public function run(): BelongsTo {
        return $this->belongsTo(ClubFeeRun::class, 'club_fee_run_id');
    }

    /** @return BelongsTo<ClubFeeAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(ClubFeeAccount::class, 'club_fee_account_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ClubFeeClaim, $this> */
    public function correctedClaim(): BelongsTo {
        return $this->belongsTo(self::class, 'corrects_claim_id');
    }

    /** @return HasMany<ClubFeeClaim, $this> */
    public function corrections(): HasMany {
        return $this->hasMany(self::class, 'corrects_claim_id');
    }

    /** @return HasMany<ClubFeeClaimItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(ClubFeeClaimItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * Zustellnachweise der Beitragsmitteilung (Mail/Download).
     *
     * @return Builder<DocumentDispatch>
     */
    public function dispatches(): Builder {
        return DocumentDispatch::query()->where('document_kind', self::DOCUMENT_KIND)->where('document_id', $this->id)->orderByDesc('id');
    }

    public function openAmount(): Money {
        return $this->total->minus($this->paid_amount);
    }

    public function isCorrection(): bool {
        return $this->kind === self::KIND_CORRECTION;
    }

    public function isOverdue(?\Carbon\CarbonInterface $today = null): bool {
        return $this->status->isOpen() && $this->due_on->lessThan(($today ?? Carbon::today())->startOfDay()) && $this->openAmount()->isPositive();
    }

    public function isDunningBlocked(): bool {
        return $this->dunning_blocked_at !== null;
    }

    /**
     * @param  Builder<ClubFeeClaim>  $query
     * @return Builder<ClubFeeClaim>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->whereIn('status', [ClubFeeClaimStatus::Open->value, ClubFeeClaimStatus::PartiallyPaid->value]);
    }
}
