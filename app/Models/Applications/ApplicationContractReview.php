<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationContractReview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Applications;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Review-Punkt („roter Punkt") einer Vertragsverhandlung (Feature 068,
 * MVP-195/196): offene Blocker verhindern den Abschluss — Abweichungen zu
 * Angebot/LV/Bedingungen werden sichtbar entschieden, nie still.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $negotiation_id
 * @property string $label
 * @property string $severity
 * @property string $status
 * @property string|null $note
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class ApplicationContractReview extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const SEVERITIES = ['info', 'important', 'blocker'];

    public const STATUSES = ['open', 'resolved', 'accepted'];

    protected $fillable = [
        'organization_id', 'negotiation_id', 'label', 'severity', 'status',
        'note', 'resolved_by', 'resolved_at',
    ];

    /** @var array<string, string> */
    protected $casts = ['resolved_at' => 'datetime'];

    /** @return BelongsTo<ApplicationContractNegotiation, $this> */
    public function negotiation(): BelongsTo {
        return $this->belongsTo(ApplicationContractNegotiation::class, 'negotiation_id');
    }
}
