<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Change.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, MorphMany};

/**
 * Change (Feature 065, MVP-157): standard/normal/emergency mit Fenster,
 * Plänen (Rollback Pflicht bei normal/emergency), Outcome + PIR.
 * Revisionssichere Historie über Auditable + system_events der Tickets.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $change_type
 * @property string $status
 * @property string|null $outcome
 * @property string|null $rollback_plan
 * @property string|null $pir_notes
 * @property \Illuminate\Support\Carbon|null $pir_done_at
 * @property array<string, mixed>|null $template_snapshot
 */
class Change extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const OUTCOMES = ['successful', 'successful_with_issues', 'failed', 'rolled_back', 'cancelled'];

    protected $fillable = [
        'organization_id', 'title', 'change_type', 'reason', 'scope',
        'risk', 'impact', 'urgency', 'window_from', 'window_to',
        'implementation_plan', 'test_plan', 'rollback_plan',
        'change_template_id', 'template_snapshot', 'status', 'outcome',
        'pir_notes', 'pir_done_at', 'problem_id', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'window_from' => 'datetime',
        'window_to' => 'datetime',
        'template_snapshot' => 'array',
        'pir_done_at' => 'datetime',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'change_type' => 'normal'];

    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany {
        return $this->morphMany(Approval::class, 'approvable')->orderBy('step');
    }

    /** @return BelongsToMany<ServiceTicket, $this> */
    public function tickets(): BelongsToMany {
        return $this->belongsToMany(ServiceTicket::class, 'change_ticket')->withTimestamps();
    }

    /** @return BelongsTo<Problem, $this> */
    public function problem(): BelongsTo {
        return $this->belongsTo(Problem::class, 'problem_id');
    }

    /** @return BelongsTo<ChangeTemplate, $this> */
    public function template(): BelongsTo {
        return $this->belongsTo(ChangeTemplate::class, 'change_template_id');
    }
}
