<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisContinuityImpact.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BCM-Sicht (Feature 070, MVP-219, BSI 200-4/ISO 22301): kritischer
 * Prozess mit RTO/RPO, Workaround, Ersatzprozess und Reststatus.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property string $process_name
 * @property int|null $rto_hours
 * @property int|null $rpo_hours
 * @property string|null $workaround
 * @property string|null $substitute_process
 * @property string $status
 * @property string|null $residual_note
 */
class CrisisContinuityImpact extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['down', 'degraded', 'workaround', 'restored'];

    protected $fillable = [
        'organization_id', 'crisis_case_id', 'process_name', 'rto_hours',
        'rpo_hours', 'workaround', 'substitute_process', 'status', 'residual_note',
    ];

    /** @var array<string, string> */
    protected $casts = ['rto_hours' => 'integer', 'rpo_hours' => 'integer'];

    /** @return BelongsTo<CrisisCase, $this> */
    public function crisisCase(): BelongsTo {
        return $this->belongsTo(CrisisCase::class, 'crisis_case_id');
    }
}
