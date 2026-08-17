<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SalesDiscountGroupOverride.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kunden-Override einer Verkaufs-Rabattgruppe (Feature 107, MVP-567):
 * ersetzt für einen Kunden den Standardsatz der Gruppe im
 * kundenindividuellen B2B-DATPREIS. `kind`/`value`-Semantik wie
 * {@see SalesDiscountGroup} (Prozent bzw. Faktor).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $sales_discount_group_id
 * @property int $customer_id
 * @property string $kind
 * @property numeric-string $value
 */
class SalesDiscountGroupOverride extends Model {
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'sales_discount_group_id',
        'customer_id',
        'kind',
        'value',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'decimal:4',
    ];

    /** @return BelongsTo<SalesDiscountGroup, $this> */
    public function group(): BelongsTo {
        return $this->belongsTo(SalesDiscountGroup::class, 'sales_discount_group_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }
}
