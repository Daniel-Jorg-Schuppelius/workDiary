<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimAssessment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Claims;

use App\Enums\Claims\{ClaimKind, ClaimVerdict};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bewertung (MVP-249): Anspruchsart + Ergebnis mit Pflichtbegründung und
 * P2-Snapshot (Fristen-/Seriennummern-/Vertragsfakten zum Zeitpunkt der
 * Bewertung — spätere Stammdatenänderungen deuten den Fall nicht um).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $claim_case_id
 * @property ClaimKind $claim_kind
 * @property ClaimVerdict $verdict
 * @property string $justification
 * @property array<string, mixed>|null $snapshot
 * @property string $status
 * @property \Illuminate\Support\Carbon $assessed_at
 */
class ClaimAssessment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'claim_case_id', 'claim_kind', 'verdict',
        'justification', 'snapshot', 'status', 'assessed_by', 'assessed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'claim_kind' => ClaimKind::class,
        'verdict' => ClaimVerdict::class,
        'snapshot' => 'array',
        'assessed_at' => 'datetime',
    ];

    /** @return BelongsTo<ClaimCase, $this> */
    public function claimCase(): BelongsTo {
        return $this->belongsTo(ClaimCase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assessor(): BelongsTo {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
