<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleSubscription.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Casts\MoneyCast;
use App\Enums\Reselling\{BillingFrequency, PeriodStatus, RenewalMode, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Models\Article;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Contract\Contract;
use App\Models\{Customer, ForeignCustomer, LexofficeArticle, Organization, User};
use App\Models\Domain\DomainProjection;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Ein Abo im Reselling-Register (Feature 152): eine weiterverkaufte
 * wiederkehrende Leistung eines Anbieters an genau einen Halter — Kunde,
 * Fremdkunde (Endkunde eines Partners, Rechnung an den Partner) oder eigener
 * Bestand (nie fakturiert). Perioden entstehen aus Start, Laufzeit und
 * Intervall (`PeriodPlanner`).
 *
 * @property int $id
 * @property int $organization_id
 * @property SubscriptionKind $kind
 * @property SubscriptionProvider $provider
 * @property string|null $external_id
 * @property string|null $external_order_id
 * @property int|null $customer_id
 * @property int|null $foreign_customer_id
 * @property bool $is_own_holding
 * @property int|null $article_id
 * @property int|null $lexoffice_article_id
 * @property string $label
 * @property string|null $company_name
 * @property int|null $import_id
 * @property CarbonImmutable|null $last_seen_at
 * @property int $quantity
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property int $term_months
 * @property BillingFrequency $interval
 * @property RenewalMode $renewal
 * @property Money|null $purchase_unit_price
 * @property Money|null $sale_unit_price
 * @property CurrencyCode $currency
 * @property SubscriptionStatus $status
 * @property int|null $successor_id
 * @property int|null $contract_id
 * @property int|null $domain_projection_id
 * @property string|null $raw_hash
 * @property string|null $sync_status
 * @property string|null $notes
 * @property int|null $created_by_user_id
 * @property-read Customer|null $customer
 * @property-read ForeignCustomer|null $foreignCustomer
 * @property-read Article|null $article
 * @property-read LexofficeArticle|null $lexofficeArticle
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResalePeriod> $periods
 */
class ResaleSubscription extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'resale_subscriptions';

    /** @var array<string, mixed> */
    protected $attributes = [
        'currency' => 'EUR',
        'quantity' => 1,
        'term_months' => 12,
        'interval' => 'yearly',
        'renewal' => 'auto',
        'status' => 'active',
        'is_own_holding' => false,
    ];

    protected $fillable = [
        'organization_id',
        'kind',
        'provider',
        'external_id',
        'external_order_id',
        'customer_id',
        'foreign_customer_id',
        'is_own_holding',
        'article_id',
        'lexoffice_article_id',
        'label',
        'company_name',
        'quantity',
        'starts_on',
        'ends_on',
        'term_months',
        'interval',
        'renewal',
        'purchase_unit_price',
        'sale_unit_price',
        'currency',
        'status',
        'successor_id',
        'contract_id',
        'domain_projection_id',
        'raw_hash',
        'import_id',
        'last_seen_at',
        'sync_status',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'kind' => SubscriptionKind::class,
        'provider' => SubscriptionProvider::class,
        'is_own_holding' => 'boolean',
        'quantity' => 'integer',
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'term_months' => 'integer',
        'interval' => BillingFrequency::class,
        'renewal' => RenewalMode::class,
        'currency' => CurrencyCode::class,
        'purchase_unit_price' => MoneyCast::class . ':currency,4',
        'sale_unit_price' => MoneyCast::class . ':currency,4',
        'status' => SubscriptionStatus::class,
        'last_seen_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ForeignCustomer, $this> */
    public function foreignCustomer(): BelongsTo {
        return $this->belongsTo(ForeignCustomer::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<LexofficeArticle, $this> */
    public function lexofficeArticle(): BelongsTo {
        return $this->belongsTo(LexofficeArticle::class);
    }

    /** @return BelongsTo<ResaleImport, $this> */
    public function import(): BelongsTo {
        return $this->belongsTo(ResaleImport::class, 'import_id');
    }

    /** @return BelongsTo<ResaleSubscription, $this> */
    public function successor(): BelongsTo {
        return $this->belongsTo(self::class, 'successor_id');
    }

    /** @return HasMany<ResaleSubscription, $this> */
    public function predecessors(): HasMany {
        return $this->hasMany(self::class, 'successor_id');
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<DomainProjection, $this> */
    public function domainProjection(): BelongsTo {
        return $this->belongsTo(DomainProjection::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ResalePeriod, $this> */
    public function periods(): HasMany {
        return $this->hasMany(ResalePeriod::class, 'subscription_id')->orderBy('starts_on');
    }

    /**
     * Abos, die für einen Kunden sichtbar sind: direkt gehalten oder von einem
     * seiner Fremdkunden (Endkunden) gehalten.
     *
     * @param  Builder<ResaleSubscription>  $query
     * @return Builder<ResaleSubscription>
     */
    public function scopeForCustomer(Builder $query, Customer $customer): Builder {
        return $query->where(function (Builder $q) use ($customer): void {
            $q->where('customer_id', $customer->id)
                ->orWhereIn('foreign_customer_id', ForeignCustomer::query()->where('customer_id', $customer->id)->select('id'));
        });
    }

    /**
     * @param  Builder<ResaleSubscription>  $query
     * @return Builder<ResaleSubscription>
     */
    public function scopePlanning(Builder $query): Builder {
        return $query->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Cancelled->value]);
    }

    /**
     * Abos ohne Halter (weder Kunde noch Fremdkunde noch eigener Bestand) — die Inbox.
     *
     * @param  Builder<ResaleSubscription>  $query
     * @return Builder<ResaleSubscription>
     */
    public function scopeUnassigned(Builder $query): Builder {
        return $query->whereNull('customer_id')->whereNull('foreign_customer_id')->where('is_own_holding', false);
    }

    /** Wer die Rechnung bekommt: der Kunde selbst oder der Partner des Fremdkunden. */
    public function billedTo(): ?Customer {
        if ($this->is_own_holding) {
            return null;
        }

        return $this->customer ?? $this->foreignCustomer?->customer;
    }

    /** Anzeigename des Halters. */
    public function holderLabel(): string {
        if ($this->is_own_holding) {
            return (string) __('resale.holder.own');
        }
        if ($this->foreignCustomer !== null) {
            return $this->foreignCustomer->name;
        }

        return $this->customer !== null ? $this->customer->name : (string) __('resale.holder.unassigned');
    }

    /** Ist der Halter geklärt? Ein Abo ohne Halter und ohne eigenen Bestand wartet auf Zuordnung. */
    public function hasHolder(): bool {
        return $this->is_own_holding || $this->customer_id !== null || $this->foreign_customer_id !== null;
    }

    /**
     * Produktanzeige: lokaler Artikel, sonst Lexoffice-Artikel (die Produktion
     * hat ihre Produkte nur dort), sonst nichts.
     */
    public function productLabel(): ?string {
        if ($this->article !== null) {
            return ($this->article->number ? $this->article->number . ' · ' : '') . $this->article->name;
        }
        if ($this->lexofficeArticle !== null) {
            return ($this->lexofficeArticle->article_number ? $this->lexofficeArticle->article_number . ' · ' : '') . $this->lexofficeArticle->name;
        }

        return null;
    }

    /** Erwarteter Verkauf je Periode (Menge × Stückpreis), wenn ein Preis hinterlegt ist. */
    public function expectedSalePerPeriod(): ?Money {
        return $this->sale_unit_price?->times($this->quantity);
    }

    public function expectedPurchasePerPeriod(): ?Money {
        return $this->purchase_unit_price?->times($this->quantity);
    }

    /** Offene Perioden mit erreichtem Beginn — das, was noch nicht berechnet ist. */
    public function openPeriodCount(): int {
        return $this->periods->filter(static fn(ResalePeriod $p): bool => $p->status === PeriodStatus::Open && ! $p->starts_on->isFuture())->count();
    }
}
