<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingFilingObligation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\{FilingObligationKind, FilingObligationStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Erledigung einer steuerlichen Meldepflicht (Feature 125, MVP-686).
 *
 * @property FilingObligationKind $kind
 * @property FilingObligationStatus $status
 */
class AccountingFilingObligation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'kind',
        'period_key',
        'due_on',
        'status',
        'submitted_at',
        'note',
        'actor_user_id',
        'notified_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => FilingObligationKind::class,
        'status' => FilingObligationStatus::class,
        'due_on' => 'date',
        'submitted_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @param  Builder<AccountingFilingObligation>  $query
     * @return Builder<AccountingFilingObligation>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->where('status', FilingObligationStatus::Open->value);
    }
}
