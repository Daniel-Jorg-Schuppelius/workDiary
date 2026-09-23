<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Approval.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Generischer Genehmigungsschritt (Feature 065, P7): EINE Mechanik für
 * ServiceRequest UND Change (approvable-Morph) — Selbstfreigabe-Sperre
 * liegt im ApprovalService.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property int $step
 * @property array<string, mixed> $approver_rule
 * @property int|null $decided_by
 * @property string|null $decision
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon|null $decided_at
 */
class Approval extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'approvable_type', 'approvable_id', 'step',
        'approver_rule', 'decided_by', 'decision', 'reason', 'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'approver_rule' => 'array',
        'decided_at' => 'datetime',
        'step' => 'integer',
    ];

    /** @return MorphTo<Model, $this> */
    public function approvable(): MorphTo {
        return $this->morphTo('approvable');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function decidedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
