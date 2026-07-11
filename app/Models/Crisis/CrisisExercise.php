<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisExercise.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\ProcedureTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Übung/Test (Feature 070, MVP-220): eigenständig — verfälscht nie echte
 * Krisenakten; verbessert Playbooks über Folgeaufgaben.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string $scenario
 * @property \Illuminate\Support\Carbon|null $exercised_at
 * @property string|null $participants
 * @property string|null $observations
 * @property string|null $deviations
 * @property string|null $effectiveness
 * @property string|null $follow_up
 * @property int|null $playbook_template_id
 * @property \Illuminate\Support\Carbon|null $next_due_on
 */
class CrisisExercise extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'title', 'scenario', 'exercised_at', 'participants',
        'observations', 'deviations', 'effectiveness', 'follow_up',
        'playbook_template_id', 'next_due_on', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['exercised_at' => 'datetime', 'next_due_on' => 'date'];

    /** @return BelongsTo<ProcedureTemplate, $this> */
    public function playbookTemplate(): BelongsTo {
        return $this->belongsTo(ProcedureTemplate::class, 'playbook_template_id');
    }
}
