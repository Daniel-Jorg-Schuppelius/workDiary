<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleAccountAllocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aufteilung der Lizenzen eines Anbieterkontos auf einen fremden Halter
 * (Feature 185, MVP-1401): „Firma B hält 5 × Exchange des Kontos
 * ABC00010 ab 18.10.2025". Die gepflegte Wahrheit je Konto und Produkt — die
 * Abtretungen je Vertrag (`resale_subscriptions.parent_id`) leitet der
 * {@see \App\Services\Reselling\Register\AllocationDeriver} daraus ab.
 *
 * @property int $id
 * @property int $organization_id
 * @property SubscriptionProvider $provider
 * @property string $provider_account
 * @property string $product_key
 * @property string $product_label
 * @property int|null $customer_id
 * @property int|null $foreign_customer_id
 * @property bool $is_own_holding
 * @property int $quantity
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Customer|null $customer
 * @property-read ForeignCustomer|null $foreignCustomer
 */
class ResaleAccountAllocation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** Präfix in `resale_subscriptions.sync_status` einer abgeleiteten Abtretung. */
    public const DERIVED_PREFIX = 'alloc:';

    protected $table = 'resale_account_allocations';

    protected $fillable = [
        'organization_id',
        'provider',
        'provider_account',
        'product_key',
        'product_label',
        'customer_id',
        'foreign_customer_id',
        'is_own_holding',
        'quantity',
        'valid_from',
        'valid_until',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'provider' => SubscriptionProvider::class,
        'is_own_holding' => 'boolean',
        'quantity' => 'integer',
        'valid_from' => 'immutable_date',
        'valid_until' => 'immutable_date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ForeignCustomer, $this> */
    public function foreignCustomer(): BelongsTo {
        return $this->belongsTo(ForeignCustomer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<ResaleAccountAllocation>  $query
     * @return Builder<ResaleAccountAllocation>
     */
    public function scopeForAccount(Builder $query, SubscriptionProvider $provider, string $account): Builder {
        return $query->where('provider', $provider->value)->where('provider_account', $account);
    }

    /** Halter als Vergleichsschlüssel (`c<id>`, `f<id>`, `own`). */
    public function holderKey(): string {
        return self::holderKeyOf($this->customer_id, $this->foreign_customer_id, $this->is_own_holding);
    }

    public static function holderKeyOf(?int $customerId, ?int $foreignCustomerId, bool $own): string {
        if ($own) {
            return 'own';
        }

        return $foreignCustomerId !== null ? 'f' . $foreignCustomerId : 'c' . (int) $customerId;
    }

    public function holderLabel(): string {
        if ($this->is_own_holding) {
            return (string) __('resale.holder.own');
        }
        if ($this->foreignCustomer !== null) {
            return $this->foreignCustomer->name;
        }

        return $this->customer !== null ? $this->customer->name : (string) __('resale.holder.unassigned');
    }

    /** Gilt die Aufteilung an diesem Tag? */
    public function coversDay(CarbonImmutable $day): bool {
        return ! $day->lessThan($this->valid_from) && ($this->valid_until === null || ! $day->greaterThan($this->valid_until));
    }

    /** Überschneidet sich die Gültigkeit mit dem Zeitraum (Ende null = offen)? */
    public function overlaps(CarbonImmutable $from, ?CarbonImmutable $to): bool {
        if ($to !== null && $to->lessThan($this->valid_from)) {
            return false;
        }

        return $this->valid_until === null || ! $this->valid_until->lessThan($from);
    }
}
