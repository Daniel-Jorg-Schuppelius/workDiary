<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingActivity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\{ControllerRole, ProcessingActivityStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Verarbeitungstaetigkeit im Verzeichnis (DSGVO Art. 30). Der Kopf traegt den
 * aktuellen Lebenszyklus-Status; die fachlichen Inhalte liegen versioniert in
 * {@see ProcessingActivityVersion} (Snapshot je Freigabe).
 *
 * @property int $id
 * @property int $organization_id
 */
class ProcessingActivity extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'privacy_processing_activities';

    protected $fillable = [
        'organization_id',
        'name',
        'purpose',
        'controller_role',
        'area',
        'status',
        'current_version_id',
        'review_due_at',
        'dsfa_required',
        'risk_level',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'controller_role' => ControllerRole::class,
        'status' => ProcessingActivityStatus::class,
        'review_due_at' => 'date',
        'dsfa_required' => 'boolean',
    ];

    /** @return HasMany<ProcessingActivityVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(ProcessingActivityVersion::class, 'activity_id')->orderByDesc('version_no');
    }

    /** @return BelongsTo<ProcessingActivityVersion, $this> */
    public function currentVersion(): BelongsTo {
        return $this->belongsTo(ProcessingActivityVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasOne<Dpia, $this> */
    public function dpia(): HasOne {
        return $this->hasOne(Dpia::class, 'activity_id');
    }

    public function isReviewOverdue(): bool {
        $due = $this->getAttribute('review_due_at');

        return $due !== null && $due->isPast();
    }
}
