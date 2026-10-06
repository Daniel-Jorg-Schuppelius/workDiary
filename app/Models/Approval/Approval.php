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

namespace App\Models\Approval;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Generischer Genehmigungsschritt (Feature 065, P7): EINE Mechanik für
 * ServiceRequest UND Change (approvable-Morph) — Selbstfreigabe-Sperre
 * liegt im ApprovalService. Eine Kette kann neu gestartet werden: die höchste
 * `round` des Objekts gilt, frühere Runden bleiben als Historie stehen.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property int $step
 * @property int $round
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
        'organization_id', 'approvable_type', 'approvable_id', 'step', 'round',
        'approver_rule', 'decided_by', 'decision', 'reason', 'decided_at',
    ];

    /** @var array<string, mixed> Frisch angelegte Schritte kennen ihre Runde ohne Nachladen. */
    protected $attributes = ['round' => 1];

    /** @var array<string, string> */
    protected $casts = [
        'approver_rule' => 'array',
        'decided_at' => 'datetime',
        'step' => 'integer',
        'round' => 'integer',
    ];

    /** @return MorphTo<Model, $this> */
    public function approvable(): MorphTo {
        return $this->morphTo('approvable');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function decidedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Nur Schritte der geltenden (höchsten) Runde ihres Objekts.
     *
     * @param Builder<static> $query
     */
    #[Scope]
    protected function currentRound(Builder $query): void {
        $query->whereNotExists(fn($newer) => $newer->from('approvals as newer_round')
            ->whereColumn('newer_round.approvable_type', 'approvals.approvable_type')
            ->whereColumn('newer_round.approvable_id', 'approvals.approvable_id')
            ->whereColumn('newer_round.round', '>', 'approvals.round'));
    }
}
