<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisDecision.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entscheidungsprotokoll (Feature 070, MVP-214): append-only mit
 * Zeitpunkt, Entscheidung, Begründung und Person.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property \Illuminate\Support\Carbon $decided_at
 * @property string $decision
 * @property string|null $rationale
 * @property int|null $decided_by
 */
class CrisisDecision extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'crisis_case_id', 'decided_at', 'decision', 'rationale', 'decided_by'];

    /** @var array<string, string> */
    protected $casts = ['decided_at' => 'datetime'];

    /** @return BelongsTo<CrisisCase, $this> */
    public function crisisCase(): BelongsTo {
        return $this->belongsTo(CrisisCase::class, 'crisis_case_id');
    }
}
