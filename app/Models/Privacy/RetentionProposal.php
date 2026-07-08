<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionProposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Lösch-Vorschlag des Retention-Scans (Restpunkt 66): ein fristüberfälliger
 * Datensatz wird NICHT direkt gelöscht, sondern zur Bestätigung vorgelegt
 * (pending → approved → purged; alternativ rejected). Jede Entscheidung ist
 * auditiert; der eigentliche Lösch-Job läuft erst nach der Bestätigung.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $area
 * @property string $subject_type
 * @property int $subject_id
 * @property \Illuminate\Support\Carbon $retention_until
 * @property string $reason
 * @property string $status
 * @property int|null $decided_by
 * @property \Illuminate\Support\Carbon|null $decided_at
 */
class RetentionProposal extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PURGED = 'purged';

    protected $fillable = [
        'organization_id',
        'area',
        'subject_type',
        'subject_id',
        'retention_until',
        'reason',
        'status',
        'decided_by',
        'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'retention_until' => 'date',
        'decided_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo('subject');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
