<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePriceEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Casts\MoneyCast;
use App\Enums\Reselling\{BillingFrequency, SubscriptionProvider};
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Organization;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einkaufskatalog-Zeile (Feature 152): Anbieterpreis je Produkt, Laufzeit,
 * Intervall und Gültigkeit — Einkauf und UVP je Stück und Intervall.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $import_id
 * @property SubscriptionProvider $provider
 * @property string $product
 * @property int $term_months
 * @property BillingFrequency $interval
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property Money $purchase_unit_price
 * @property Money|null $list_unit_price
 * @property CurrencyCode $currency
 */
class ResalePriceEntry extends Model {
    use BelongsToOrganization;

    protected $table = 'resale_price_catalog';

    protected $fillable = [
        'organization_id',
        'import_id',
        'provider',
        'product',
        'term_months',
        'interval',
        'valid_from',
        'valid_to',
        'purchase_unit_price',
        'list_unit_price',
        'currency',
    ];

    protected $casts = [
        'provider' => SubscriptionProvider::class,
        'term_months' => 'integer',
        'interval' => BillingFrequency::class,
        'valid_from' => 'immutable_date',
        'valid_to' => 'immutable_date',
        'currency' => CurrencyCode::class,
        'purchase_unit_price' => MoneyCast::class . ':currency,4',
        'list_unit_price' => MoneyCast::class . ':currency,4',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ResaleImport, $this> */
    public function import(): BelongsTo {
        return $this->belongsTo(ResaleImport::class, 'import_id');
    }

    /**
     * Am Stichtag gültige Zeilen.
     *
     * @param  Builder<ResalePriceEntry>  $query
     * @return Builder<ResalePriceEntry>
     */
    public function scopeValidOn(Builder $query, CarbonImmutable $date): Builder {
        // Halboffen gegen Tagesgrenzen (SQLite vergleicht Strings, DateRange-Regel).
        return $query->where('valid_from', '<', DateRange::dayAfter($date))
            ->where(static fn(Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($date)));
    }
}
