<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityMeasure.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verbesserungsmaßnahme (Feature 071, MVP-229): erwartete Wirkung,
 * Aufwand/Kosten, Frist, Nachweis und Wirksamkeitsprüfung (Vorbild
 * IsmsCorrectiveAction).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $assessment_id
 * @property string $title
 * @property string|null $description
 * @property string|null $expected_impact
 * @property string $effort
 * @property string|null $cost_estimate
 * @property int|null $responsible_user_id
 * @property \Illuminate\Support\Carbon|null $due_on
 * @property string $status
 * @property string|null $evidence_note
 * @property string|null $effectiveness
 * @property string|null $effectiveness_note
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 */
class SustainabilityMeasure extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['proposed', 'approved', 'in_progress', 'done', 'discarded'];

    protected $fillable = [
        'organization_id', 'assessment_id', 'title', 'description',
        'expected_impact', 'effort', 'cost_estimate', 'responsible_user_id',
        'due_on', 'status', 'evidence_note', 'effectiveness',
        'effectiveness_note', 'reviewed_by', 'reviewed_at', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'cost_estimate' => 'decimal:2',
        'due_on' => 'date',
        'reviewed_at' => 'datetime',
    ];

    /** @return BelongsTo<SustainabilityAssessment, $this> */
    public function assessment(): BelongsTo {
        return $this->belongsTo(SustainabilityAssessment::class, 'assessment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
