<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaymentAllocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\AllocationKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Finance\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Bestätigte Zahlungszuordnung (Feature 045, „Priorität 3"). Reversibel über
 * SoftDelete (unmatch), ohne den Bankumsatz zu verändern.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bank_transaction_id
 * @property string $allocatable_type
 * @property int $allocatable_id
 * @property string $amount
 * @property AllocationKind $kind
 * @property string|null $note
 * @property int|null $confirmed_by_user_id
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentAllocation extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<PaymentAllocationFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'bank_transaction_id',
        'allocatable_type',
        'allocatable_id',
        'amount',
        'kind',
        'note',
        'confirmed_by_user_id',
        'confirmed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'kind' => AllocationKind::class,
        'confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<BankTransaction, $this> */
    public function transaction(): BelongsTo {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function allocatable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
