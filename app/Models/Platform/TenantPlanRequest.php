<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantPlanRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Platform;

use App\Enums\Platform\TenantPlanRequestStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tarifwechsel-Anfrage (MVP-957): der Mandant fragt an, der Betreiber stellt
 * die Lizenz aus und schließt die Anfrage.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $requested_plan
 * @property list<string>|null $requested_addons
 * @property string|null $note
 * @property TenantPlanRequestStatus $status
 * @property int|null $requester_user_id
 * @property int|null $decider_user_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 */
class TenantPlanRequest extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'requested_plan', 'requested_addons', 'note', 'status', 'requester_user_id', 'decider_user_id', 'decided_at', 'decision_note'];

    /** @var array<string, string> */
    protected $casts = [
        'requested_addons' => 'array',
        'status' => TenantPlanRequestStatus::class,
        'decided_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo {
        return $this->belongsTo(User::class, 'requester_user_id');
    }
}
