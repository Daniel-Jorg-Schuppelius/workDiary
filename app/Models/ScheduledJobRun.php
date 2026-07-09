<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledJobRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Einzellauf eines Registry-Jobs (Feature 067, MVP-177). Massendaten —
 * bewusst ohne Audit; Retention übernimmt scheduler:watchdog.
 *
 * @property int $id
 * @property string $job_key
 * @property \Carbon\CarbonImmutable $started_at
 * @property \Carbon\CarbonImmutable|null $finished_at
 * @property string $status
 * @property int|null $duration_ms
 * @property int|null $exit_code
 */
class ScheduledJobRun extends Model {
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'scheduled_job_runs';

    protected $fillable = ['job_key', 'started_at', 'finished_at', 'status', 'duration_ms', 'exit_code'];

    /** @var array<string, string> */
    protected $casts = [
        'started_at' => 'immutable_datetime',
        'finished_at' => 'immutable_datetime',
        'duration_ms' => 'integer',
        'exit_code' => 'integer',
    ];
}
