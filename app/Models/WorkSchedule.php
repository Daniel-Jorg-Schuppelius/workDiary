<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkSchedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\WorkSchedule\ScheduleType;
use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property ScheduleType $schedule_type
 * @property int $weekly_minutes
 * @property int $daily_target_minutes
 * @property array<int, int> $working_days
 * @property array<int|string, array<string, mixed>>|null $day_targets
 * @property string|null $core_start
 * @property string|null $core_end
 * @property string|null $frame_start
 * @property string|null $frame_end
 * @property int $break_after_minutes
 * @property int $break_minutes
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property bool $exists
 */
class WorkSchedule extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @param array<string, mixed> $attributes */
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
    }

    /** @var array<string, mixed> */
    protected $attributes = [
        'schedule_type' => 'flextime',
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'schedule_type',
        'weekly_minutes',
        'daily_target_minutes',
        'working_days',
        'day_targets',
        'core_start',
        'core_end',
        'frame_start',
        'frame_end',
        'break_after_minutes',
        'break_minutes',
        'valid_from',
        'valid_to',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'schedule_type' => ScheduleType::class,
        'working_days' => 'array',
        'day_targets' => 'array',
        'weekly_minutes' => 'integer',
        'daily_target_minutes' => 'integer',
        'break_after_minutes' => 'integer',
        'break_minutes' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * Ob an diesem ISO-Wochentag (1=Mo … 7=So) gearbeitet wird.
     * Bei wochentagsweisem Typ zählt ein Tag mit hinterlegtem Soll > 0,
     * sonst die `working_days`-Liste.
     */
    public function appliesOnWeekday(int $isoDow): bool {
        if ($this->schedule_type === ScheduleType::PerWeekday) {
            return (int) ($this->dayTarget($isoDow)['minutes'] ?? 0) > 0;
        }

        return in_array($isoDow, $this->working_days ?? [], true);
    }

    /**
     * Zentrale Soll-Ermittlung je ISO-Wochentag in Minuten. Einzige Quelle für
     * FlexCalculator, Anwesenheits- und Plan-/Ist-Reports.
     */
    public function targetMinutesForWeekday(int $isoDow): int {
        return match ($this->schedule_type) {
            ScheduleType::Trust => 0,
            ScheduleType::PerWeekday => (int) ($this->dayTarget($isoDow)['minutes'] ?? 0),
            ScheduleType::Weekly => $this->appliesOnWeekday($isoDow)
                ? (int) round($this->weekly_minutes / max(1, count($this->working_days ?? [])))
                : 0,
            ScheduleType::Flextime => $this->appliesOnWeekday($isoDow)
                ? (int) $this->daily_target_minutes
                : 0,
        };
    }

    /** Ob dieser Plan überhaupt ein Soll führt (false = Vertrauensarbeitszeit). */
    public function tracksTarget(): bool {
        return $this->schedule_type->tracksTarget();
    }

    /**
     * Pro-Wochentag-Vorgabe (JSON-Schlüssel sind Strings nach dem Cast).
     *
     * @return array<string, mixed>|null
     */
    private function dayTarget(int $isoDow): ?array {
        $map = $this->day_targets ?? [];
        $entry = $map[$isoDow] ?? $map[(string) $isoDow] ?? null;

        return is_array($entry) ? $entry : null;
    }
}
