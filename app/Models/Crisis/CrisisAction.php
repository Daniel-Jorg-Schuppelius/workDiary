<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Krisenmaßnahme (Feature 070, MVP-216): Verantwortliche, Frist,
 * Abhängigkeit, Nachweis und Eskalationsmarker.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property string $title
 * @property string|null $description
 * @property int|null $assignee_id
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property string $priority
 * @property string $status
 * @property int|null $depends_on_id
 * @property string|null $evidence_note
 * @property \Illuminate\Support\Carbon|null $escalated_at
 */
class CrisisAction extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['open', 'in_progress', 'done', 'cancelled'];

    protected $fillable = [
        'organization_id', 'crisis_case_id', 'title', 'description',
        'assignee_id', 'due_at', 'priority', 'status', 'depends_on_id',
        'evidence_note', 'escalated_at',
    ];

    /** @var array<string, string> */
    protected $casts = ['due_at' => 'datetime', 'escalated_at' => 'datetime'];

    /** @return BelongsTo<CrisisCase, $this> */
    public function crisisCase(): BelongsTo {
        return $this->belongsTo(CrisisCase::class, 'crisis_case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<CrisisAction, $this> */
    public function dependsOn(): BelongsTo {
        return $this->belongsTo(self::class, 'depends_on_id');
    }
}
