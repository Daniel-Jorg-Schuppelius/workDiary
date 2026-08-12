<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeRuleResult.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Surcharge;

use App\Models\Attendance;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\{TimeExport, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MVP-513 (Feature 103): persistiertes Regel-Ergebnis je Zeitdatensatz —
 * welche Regel hat auf welchem Intervall wie viele Minuten mit welcher
 * Lohnart erzeugt, samt Snapshot des angewandten Regelstands
 * (`calculation_snapshot`). Abgeleitete, reproduzierbare Daten: bewusst
 * NICHT Auditable und nicht GoBD-guarded — das revisionssichere Original
 * bleibt der Zeitexport (`time_exports` + `payload_hash`); Neuberechnungen
 * laufen ausschließlich auditiert über `rules:recalculate`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property int|null $attendance_id
 * @property int $surcharge_rule_id
 * @property int|null $time_export_id
 * @property \Illuminate\Support\Carbon $date
 * @property int $minutes
 * @property string $wage_type_code
 * @property string $percentage
 * @property array<string, mixed> $calculation_snapshot
 */
class TimeRuleResult extends Model {
    use BelongsToOrganization;

    protected $table = 'time_rule_results';

    protected $fillable = [
        'organization_id',
        'user_id',
        'attendance_id',
        'surcharge_rule_id',
        'time_export_id',
        'date',
        'minutes',
        'wage_type_code',
        'percentage',
        'calculation_snapshot',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'minutes' => 'integer',
        'percentage' => 'decimal:2',
        'calculation_snapshot' => 'array',
    ];

    /** @return BelongsTo<SurchargeRule, $this> */
    public function rule(): BelongsTo {
        return $this->belongsTo(SurchargeRule::class, 'surcharge_rule_id');
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TimeExport, $this> */
    public function export(): BelongsTo {
        return $this->belongsTo(TimeExport::class, 'time_export_id');
    }
}
