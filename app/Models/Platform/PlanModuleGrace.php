<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanModuleGrace.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Eintrag im Downgrade-/Karenz-Ledger (Tabelle plan_module_grace).
 * Bewusst OHNE Org-Global-Scope: wird in Observer-/Command-Kontexten
 * organisationsuebergreifend gelesen und immer explizit nach organization_id
 * gefiltert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $module
 * @property \Illuminate\Support\Carbon $lost_at
 * @property \Illuminate\Support\Carbon $grace_until
 * @property \Illuminate\Support\Carbon|null $purged_at
 */
class PlanModuleGrace extends Model {
    protected $table = 'plan_module_grace';

    protected $fillable = [
        'organization_id',
        'module',
        'lost_at',
        'grace_until',
        'purged_at',
    ];

    protected $casts = [
        'lost_at' => 'datetime',
        'grace_until' => 'datetime',
        'purged_at' => 'datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }
}
