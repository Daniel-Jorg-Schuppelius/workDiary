<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourProposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Diary;

use App\Enums\Tour\TourProposalStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Database\Factories\Diary\TourProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tourvorschlag (MVP-1176): Problem und Lösung eines Solver-Laufs mit
 * Kennzahlen, bewusst übernommen oder mit Grund verworfen. Für eine Tour
 * (`tour_id`) oder einen Tag der Leitstelle (`tour_id` leer, MVP-1179).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $tour_id
 * @property Carbon $proposal_date
 * @property string|null $solver
 * @property bool $is_estimate
 * @property array<string, mixed>|null $problem
 * @property array<string, mixed>|null $plan
 * @property array<string, mixed>|null $metrics
 * @property TourProposalStatus $status
 * @property string|null $failure
 * @property string|null $decision_note
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property int|null $created_by
 */
class TourProposal extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<TourProposalFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'tour_id',
        'proposal_date',
        'solver',
        'is_estimate',
        'problem',
        'plan',
        'metrics',
        'status',
        'failure',
        'decision_note',
        'decided_by',
        'decided_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'proposal_date' => 'date',
        'is_estimate' => 'boolean',
        'problem' => 'array',
        'plan' => 'array',
        'metrics' => 'array',
        'status' => TourProposalStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
