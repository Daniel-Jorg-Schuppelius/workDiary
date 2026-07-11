<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceDeadline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetFinance;

use App\Enums\AssetFinance\AssetFinanceDeadlineKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vertragsfrist (MVP-273): Kündigung, Verlängerung, Kaufoption, Rückgabe,
 * Endprüfung, Versicherung, Service, Dokumentablauf — mit Vorwarnzeit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_finance_contract_id
 * @property AssetFinanceDeadlineKind $kind
 * @property \Illuminate\Support\Carbon $due_on
 * @property int $warn_days_before
 * @property string $status
 */
class AssetFinanceDeadline extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['open', 'done', 'missed'];

    protected $fillable = [
        'organization_id', 'asset_finance_contract_id', 'kind', 'due_on',
        'warn_days_before', 'status', 'responsible_user_id', 'note',
        'done_at', 'done_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => AssetFinanceDeadlineKind::class,
        'due_on' => 'date',
        'warn_days_before' => 'integer',
        'done_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->where('status', 'open');
    }

    public function isDueForWarning(): bool {
        return $this->status === 'open'
            && $this->due_on->copy()->subDays($this->warn_days_before)->startOfDay()->isPast();
    }

    /** @return BelongsTo<AssetFinanceContract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(AssetFinanceContract::class, 'asset_finance_contract_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
