<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenterRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kostenstellen-Regel für den geprüften Zeitexport (MVP-019, Rang 35 —
 * rescoped auf User/Team): genau eine Quelle je Regel — Benutzer ODER Team;
 * beide leer = Org-Default. Präzedenz: Benutzer > Team (höchste Priorität) >
 * Default. Aufgelöst vom {@see \App\Services\TimeExport\CostCenterResolver}.
 */
class CostCenterRule extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'team_id',
        'cost_center',
        'priority',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'priority' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo {
        return $this->belongsTo(Team::class);
    }
}
