<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Antrag auf nachträgliche Korrektur eines Zeitdatensatzes (MVP-017).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int $requested_by_user_id
 * @property Carbon $scope_date
 * @property TimeCorrectionStatus $status
 * @property string $reason
 * @property Carbon|null $decided_at
 * @property int|null $decided_by_user_id
 * @property string|null $decision_note
 * @property Carbon|null $applied_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TimeCorrectionItem> $items
 */
class TimeCorrectionRequest extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'requested_by_user_id',
        'scope_date',
        'status',
        'reason',
        'decided_at',
        'decided_by_user_id',
        'decision_note',
        'applied_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'scope_date' => 'date',
        'status' => TimeCorrectionStatus::class,
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** @return HasMany<TimeCorrectionItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(TimeCorrectionItem::class);
    }
}
