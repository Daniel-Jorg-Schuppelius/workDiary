<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceOption.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetFinance;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vertragsoption (MVP-272/276): Kauf-, Verlängerungs- oder vorzeitige
 * Kündigungsoption mit Ausübungsfenster — Ausübung ist auditpflichtig.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_finance_contract_id
 * @property string $kind
 * @property \Illuminate\Support\Carbon|null $exercisable_from
 * @property \Illuminate\Support\Carbon|null $exercisable_until
 * @property \Illuminate\Support\Carbon|null $exercised_at
 */
class AssetFinanceOption extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const KINDS = ['purchase', 'extension', 'early_termination'];

    protected $fillable = [
        'organization_id', 'asset_finance_contract_id', 'kind',
        'exercisable_from', 'exercisable_until', 'amount', 'exercised_at',
        'exercised_by', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'exercisable_from' => 'date',
        'exercisable_until' => 'date',
        'amount' => 'decimal:2',
        'exercised_at' => 'datetime',
    ];

    public function isExercisable(): bool {
        if ($this->exercised_at !== null) {
            return false;
        }

        $today = now()->startOfDay();

        return ($this->exercisable_from === null || $this->exercisable_from <= $today)
            && ($this->exercisable_until === null || $this->exercisable_until->endOfDay() >= $today);
    }

    /** @return BelongsTo<AssetFinanceContract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(AssetFinanceContract::class, 'asset_finance_contract_id');
    }

    /** @return BelongsTo<User, $this> */
    public function exercisedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'exercised_by');
    }
}
