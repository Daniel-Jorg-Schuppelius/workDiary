<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledJobState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregierter Laufzeit-Zustand je Registry-Job (Feature 067, MVP-177)
 * für schnelle Admin-/Diagnose-Anzeige ohne Scan über die runs-Tabelle.
 *
 * @property int $id
 * @property string $job_key
 * @property \Carbon\CarbonImmutable|null $last_started_at
 * @property \Carbon\CarbonImmutable|null $last_success_at
 * @property \Carbon\CarbonImmutable|null $last_failure_at
 * @property int $consecutive_failures
 * @property int|null $last_duration_ms
 * @property string|null $last_status
 * @property \Carbon\CarbonImmutable|null $overdue_notified_at
 */
class ScheduledJobState extends Model {
    protected $table = 'scheduled_job_states';

    protected $fillable = [
        'job_key',
        'last_started_at',
        'last_success_at',
        'last_failure_at',
        'consecutive_failures',
        'last_duration_ms',
        'last_status',
        'overdue_notified_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_started_at' => 'immutable_datetime',
        'last_success_at' => 'immutable_datetime',
        'last_failure_at' => 'immutable_datetime',
        'consecutive_failures' => 'integer',
        'last_duration_ms' => 'integer',
        'overdue_notified_at' => 'immutable_datetime',
    ];

    public static function forJob(string $jobKey): self {
        return self::query()->firstOrNew(['job_key' => $jobKey]);
    }
}
