<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingFiscalYear.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\AccountingPeriodStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Geschäftsjahr der lokalen Buchhaltung (Feature 125, MVP-671).
 *
 * @property AccountingPeriodStatus $status
 */
class AccountingFiscalYear extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'label',
        'starts_on',
        'ends_on',
        'status',
        'closed_at',
        'closed_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AccountingPeriodStatus::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
        'closed_at' => 'datetime',
    ];

    /** @return HasMany<AccountingPeriod, $this> */
    public function periods(): HasMany {
        return $this->hasMany(AccountingPeriod::class, 'accounting_fiscal_year_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
