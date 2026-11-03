<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Project;

use App\Enums\Project\ShortfallStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Finance\BillingTransferPosition;
use App\Models\Platform\User;
use Database\Factories\Project\ProjectTimeShortfallFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tagesbefund „unter Soll“ je Projekt und Person (Feature 194). Der Nachtlauf
 * legt ihn an, erledigt ihn bei Nachbuchung und öffnet ihn wieder, wenn die
 * Zeit erneut unter das Soll fällt; Bestätigen hält ihn als gesehen fest.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $project_id
 * @property int $user_id
 * @property Carbon $date
 * @property int $target_minutes
 * @property int $booked_minutes
 * @property int $shortfall_minutes
 * @property int $tolerance_minutes
 * @property ShortfallStatus $status
 * @property int|null $acknowledged_by
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $notified_at
 * @property string|null $note
 * @property int|null $billed_minutes
 * @property Carbon|null $billed_at
 * @property int|null $billing_transfer_position_id
 * @property-read Project $project
 * @property-read User $user
 */
class ProjectTimeShortfall extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<ProjectTimeShortfallFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'project_id', 'user_id', 'date', 'target_minutes', 'booked_minutes',
        'shortfall_minutes', 'tolerance_minutes', 'status', 'acknowledged_by', 'acknowledged_at',
        'resolved_at', 'notified_at', 'note', 'billed_minutes', 'billed_at', 'billing_transfer_position_id',
    ];

    protected $casts = [
        'date' => 'date',
        'target_minutes' => 'integer',
        'booked_minutes' => 'integer',
        'shortfall_minutes' => 'integer',
        'tolerance_minutes' => 'integer',
        'status' => ShortfallStatus::class,
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'notified_at' => 'datetime',
        'billed_minutes' => 'integer',
        'billed_at' => 'datetime',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return BelongsTo<BillingTransferPosition, $this> */
    public function billingTransferPosition(): BelongsTo {
        return $this->belongsTo(BillingTransferPosition::class);
    }

    /**
     * Offen oder bestätigt — alles, was noch nicht durch Nachbuchung erledigt ist.
     *
     * @param  Builder<ProjectTimeShortfall>  $query
     * @return Builder<ProjectTimeShortfall>
     */
    public function scopeUnresolved(Builder $query): Builder {
        return $query->where('status', '!=', ShortfallStatus::Resolved->value);
    }

    /**
     * @param  Builder<ProjectTimeShortfall>  $query
     * @return Builder<ProjectTimeShortfall>
     */
    public function scopeOpen(Builder $query): Builder {
        return $query->where('status', ShortfallStatus::Open->value);
    }

    public function isBilled(): bool {
        return $this->billed_minutes !== null;
    }
}
