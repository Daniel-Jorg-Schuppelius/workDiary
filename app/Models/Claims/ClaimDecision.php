<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimDecision.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entscheidung (MVP-249): angenommen/abgelehnt/Kulanz/teilweise — mit
 * Pflichtbegründung und Snapshot der aktiven Bewertung (Auditspur).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_case_id
 * @property string $decision
 * @property string $justification
 * @property array<string, mixed>|null $snapshot
 * @property \Illuminate\Support\Carbon $decided_at
 */
class ClaimDecision extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const DECISIONS = ['accepted', 'rejected', 'goodwill', 'partial'];

    protected $fillable = [
        'organization_id', 'claim_case_id', 'decision', 'justification',
        'snapshot', 'decided_by', 'decided_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'snapshot' => 'array',
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
