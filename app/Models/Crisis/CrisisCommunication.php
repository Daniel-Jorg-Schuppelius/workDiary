<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisCommunication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Crisis;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Krisenkommunikation (Feature 070, MVP-217): Entwurf → Freigabe →
 * Aussendung bleiben GETRENNT nachvollziehbar; externe Aussendung nur
 * nach Freigabe (crisis.approve).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $crisis_case_id
 * @property string $audience
 * @property string $subject
 * @property string $body
 * @property string $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property string|null $channel
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property int|null $created_by
 */
class CrisisCommunication extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const AUDIENCES = ['internal', 'customers', 'suppliers', 'authorities', 'dpa', 'insurer', 'public'];

    protected $fillable = [
        'organization_id', 'crisis_case_id', 'audience', 'subject', 'body',
        'status', 'approved_by', 'approved_at', 'channel', 'sent_at', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['approved_at' => 'datetime', 'sent_at' => 'datetime'];

    /** @return BelongsTo<CrisisCase, $this> */
    public function crisisCase(): BelongsTo {
        return $this->belongsTo(CrisisCase::class, 'crisis_case_id');
    }
}
