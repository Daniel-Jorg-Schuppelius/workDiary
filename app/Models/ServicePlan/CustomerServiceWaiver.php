<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerServiceWaiver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\ServicePlan;

use App\Casts\PeriodKeyCast;
use App\Models\Classification\EntryType;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\ServicePlan\Dto\ServiceKey;
use App\Support\Period\PeriodKey;
use Database\Factories\ServicePlan\CustomerServiceWaiverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * „Entfällt“ im Leistungsplan (Feature 199, MVP-1638): eine Leistung des
 * Kunden wird in einer Periode nicht erbracht (Grund Pflicht, von, am). Nur
 * für Zellen ohne Auftrag — sobald ein Auftrag existiert, trägt er den Stand.
 * Geschrieben wird über {@see \App\Services\ServicePlan\ServicePlanCells}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int $entry_type_id
 * @property string|null $product_group_code
 * @property PeriodKey $period_key
 * @property string $reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerServiceWaiver extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<CustomerServiceWaiverFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'entry_type_id',
        'product_group_code',
        'period_key',
        'reason',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_key' => PeriodKeyCast::class,
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo {
        return $this->belongsTo(EntryType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function serviceKey(): ServiceKey {
        return new ServiceKey((int) $this->entry_type_id, $this->product_group_code);
    }
}
