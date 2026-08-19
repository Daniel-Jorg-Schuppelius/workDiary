<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Patrol;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Scan-Nachweis eines Kontrollpunkts (Feature 089): Ist-Zeit + Abweichung
 * vom Soll-Fenster. Bewusst ohne Positionsdaten — der Scan belegt Punkt und
 * Zeit, kein Dauer-Tracking.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $patrol_run_id
 * @property int $patrol_checkpoint_id
 * @property Carbon $scanned_at
 * @property int $delta_minutes
 * @property bool $in_window
 */
class PatrolScan extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id', 'patrol_run_id', 'patrol_checkpoint_id',
        'scanned_at', 'delta_minutes', 'in_window',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'scanned_at' => 'datetime',
        'delta_minutes' => 'integer',
        'in_window' => 'boolean',
    ];

    /** @return BelongsTo<PatrolCheckpoint, $this> */
    public function checkpoint(): BelongsTo {
        return $this->belongsTo(PatrolCheckpoint::class, 'patrol_checkpoint_id');
    }
}
