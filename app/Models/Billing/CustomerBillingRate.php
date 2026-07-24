<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerBillingRate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Billing;

use App\Enums\Billing\BillingRateDayType;
use App\Models\ActivityCategory;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Stundensatz einer Kunden-Sonderkondition (Feature 098) je Tätigkeits-
 * kategorie × Tagtyp; activity_category_id=NULL ist der Fallback für alle
 * Kategorien. valid_from/valid_until erlauben Satz-Historie.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_billing_agreement_id
 * @property int|null $activity_category_id
 * @property BillingRateDayType $day_type
 * @property float $hourly_rate
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 */
class CustomerBillingRate extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected static function booted(): void {
        static::saved(fn () => \App\Services\Billing\AgreementRateResolver::flush());
        static::deleted(fn () => \App\Services\Billing\AgreementRateResolver::flush());
    }

    protected $fillable = [
        'organization_id',
        'customer_billing_agreement_id',
        'activity_category_id',
        'day_type',
        'hourly_rate',
        'valid_from',
        'valid_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'day_type' => BillingRateDayType::class,
        'hourly_rate' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<CustomerBillingAgreement, $this> */
    public function agreement(): BelongsTo {
        return $this->belongsTo(CustomerBillingAgreement::class, 'customer_billing_agreement_id');
    }

    /** @return BelongsTo<ActivityCategory, $this> */
    public function activityCategory(): BelongsTo {
        return $this->belongsTo(ActivityCategory::class);
    }
}
