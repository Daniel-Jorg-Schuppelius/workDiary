<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\AccountingPeriodStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buchungsperiode (Feature 125, MVP-671). Der Abschlussworkflow selbst kommt
 * mit MVP-677; hier entsteht die Struktur, gegen die MVP-672 sein
 * Perioden-Guard prüft.
 *
 * @property AccountingPeriodStatus $status
 */
class AccountingPeriod extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'accounting_fiscal_year_id',
        'sequence',
        'starts_on',
        'ends_on',
        'status',
        'soft_closed_at',
        'closed_at',
        'closed_by',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AccountingPeriodStatus::class,
        'sequence' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'soft_closed_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    /** @return BelongsTo<AccountingFiscalYear, $this> */
    public function fiscalYear(): BelongsTo {
        return $this->belongsTo(AccountingFiscalYear::class, 'accounting_fiscal_year_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Periode, die dieses Buchungsdatum enthält.
     *
     * @param  Builder<AccountingPeriod>  $query
     * @return Builder<AccountingPeriod>
     */
    public function scopeCovering(Builder $query, CarbonInterface $date): Builder {
        return $query->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString());
    }
}
