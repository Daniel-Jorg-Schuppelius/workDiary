<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Rental;

use App\Casts\MoneyCast;
use App\Enums\Rental\RentalMode;
use App\Models\Asset\Asset;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verleihprofil eines Assets (MVP-259): macht ein Asset leihfähig und
 * trägt Gerätegruppe, Pufferzeiten, Zubehör und Mindestzustand. Interner
 * Asset-Checkout (Dienstmittel) bleibt davon unberührt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property bool $is_rentable
 * @property string|null $group_code
 * @property int $buffer_before_hours
 * @property int $buffer_after_hours
 * @property bool $requires_inspection
 * @property array<int, string>|null $accessories
 * @property RentalMode|null $rental_mode Wohnung (Branchenprofil Vermieter); null = Leihgerät
 * @property \CommonToolkit\ValueObjects\Money|null $rent_amount Kaltmiete je Monat
 * @property \CommonToolkit\ValueObjects\Money|null $ancillary_amount Nebenkostenvorauszahlung je Monat
 */
class RentalProfile extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** Wie der Spaltenstandard, damit ein ungeladenes Modell die Währung kennt. */
    protected $attributes = ['currency' => 'EUR'];

    protected $fillable = [
        'organization_id', 'asset_id', 'is_rentable', 'portal_bookable', 'group_code',
        'home_site_label', 'buffer_before_hours', 'buffer_after_hours',
        'requires_inspection', 'min_condition', 'accessories',
        'default_rate_card_id', 'notes',
        'rental_mode', 'public_description', 'max_guests', 'minimum_nights',
        'check_in_time', 'check_out_time', 'rent_amount', 'ancillary_amount', 'currency',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_rentable' => 'boolean',
        'portal_bookable' => 'boolean',
        'requires_inspection' => 'boolean',
        'buffer_before_hours' => 'integer',
        'buffer_after_hours' => 'integer',
        'accessories' => 'array',
        'rental_mode' => RentalMode::class,
        'max_guests' => 'integer',
        'minimum_nights' => 'integer',
        'rent_amount' => MoneyCast::class . ':currency,2',
        'ancillary_amount' => MoneyCast::class . ':currency,2',
        'currency' => CurrencyCode::class,
    ];

    /** @param Builder<self> $query */
    public function scopeRentable(Builder $query): void {
        $query->where('is_rentable', true);
    }

    /**
     * Wohnungen des Branchenprofils Vermieter, ohne Leihgeräte.
     *
     * @param Builder<self> $query
     */
    public function scopeHousing(Builder $query): void {
        $query->whereIn('rental_mode', RentalMode::values());
    }

    /** @param Builder<self> $query */
    public function scopeLongTermRentable(Builder $query): void {
        $query->whereIn('rental_mode', [RentalMode::LongTerm->value, RentalMode::Both->value]);
    }

    public function isHousing(): bool {
        return $this->rental_mode !== null;
    }

    /**
     * Fürs Portal-Sortiment freigegeben (MVP-714, Default-Deny) — und leihfähig.
     *
     * @param Builder<self> $query
     */
    public function scopePortalBookable(Builder $query): void {
        $query->where('is_rentable', true)->where('portal_bookable', true);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<RentalRateCard, $this> */
    public function defaultRateCard(): BelongsTo {
        return $this->belongsTo(RentalRateCard::class, 'default_rate_card_id');
    }
}
