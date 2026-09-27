<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractIndexation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Enums\Contract\ContractIndexationStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Indexanpassung eines Vertrags (MVP-952): Vorschlag aus Basis- und
 * aktuellem Indexwert, erst mit der Übernahme ändert sich der Vertragswert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $contract_id
 * @property ContractIndexationStatus $status
 * @property Carbon $base_period_on
 * @property numeric-string $base_value
 * @property Carbon $index_period_on
 * @property numeric-string $index_value
 * @property numeric-string $change_percent
 * @property numeric-string $old_amount
 * @property numeric-string $new_amount
 * @property string $currency
 * @property Carbon|null $effective_on
 * @property int|null $decider_user_id
 * @property Carbon|null $decided_at
 * @property string|null $note
 */
class ContractIndexation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'contract_id', 'status', 'base_period_on', 'base_value', 'index_period_on', 'index_value',
        'change_percent', 'old_amount', 'new_amount', 'currency', 'effective_on', 'decider_user_id', 'decided_at', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ContractIndexationStatus::class,
        'base_period_on' => 'date',
        'base_value' => 'decimal:1',
        'index_period_on' => 'date',
        'index_value' => 'decimal:1',
        'change_percent' => 'decimal:4',
        'old_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'effective_on' => 'date',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo {
        return $this->belongsTo(User::class, 'decider_user_id');
    }
}
