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
 *
 * `cost_center_id` verweist auf die Stammdaten (Feature 069); der String
 * `cost_center` bleibt Code-Snapshot/Fallback (nullOnDelete). Effektiv gilt
 * der Stammdaten-Code, siehe {@see self::effectiveCode()}.
 *
 * @property int|null $cost_center_id
 * @property string $cost_center
 * @property-read CostCenter|null $costCenter
 */
class CostCenterRule extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'team_id',
        'cost_center_id',
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

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Exportierter Kostenstellen-Code: Stammdaten führen (Umbenennung wirkt
     * sofort auf künftige Exporte), der String-Snapshot deckt Regeln ohne
     * bzw. mit gelöschtem Stammsatz.
     */
    public function effectiveCode(): string {
        return $this->costCenter->code ?? $this->cost_center;
    }
}
