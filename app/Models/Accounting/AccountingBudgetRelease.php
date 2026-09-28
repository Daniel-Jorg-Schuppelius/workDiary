<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingBudgetRelease.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\BudgetReleaseStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Finance\CostCenter;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Freigabe eines Budgets je Geschäftsjahr und Kostenstelle (MVP-983);
 * freigegeben ist es gegen Änderung gesperrt, ein Nachtrag öffnet es mit Grund.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $fiscal_year
 * @property int|null $cost_center_id
 * @property BudgetReleaseStatus $status
 * @property Carbon|null $released_at
 * @property int|null $released_by
 * @property Carbon|null $reopened_at
 * @property int|null $reopened_by
 * @property string|null $reopen_reason
 */
class AccountingBudgetRelease extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'fiscal_year', 'cost_center_id', 'status', 'released_at', 'released_by', 'reopened_at', 'reopened_by', 'reopen_reason'];

    /** @var array<string, string> */
    protected $casts = [
        'fiscal_year' => 'integer',
        'status' => BudgetReleaseStatus::class,
        'released_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo {
        return $this->belongsTo(User::class, 'released_by');
    }
}
