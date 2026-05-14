<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property int $weekly_minutes
 * @property int $daily_target_minutes
 * @property array<int, int> $working_days
 * @property string|null $core_start
 * @property string|null $core_end
 * @property string|null $frame_start
 * @property string|null $frame_end
 * @property int $break_after_minutes
 * @property int $break_minutes
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 */
class WorkSchedule extends Model
{
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'weekly_minutes',
        'daily_target_minutes',
        'working_days',
        'core_start',
        'core_end',
        'frame_start',
        'frame_end',
        'break_after_minutes',
        'break_minutes',
        'valid_from',
        'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'working_days' => 'array',
            'weekly_minutes' => 'integer',
            'daily_target_minutes' => 'integer',
            'break_after_minutes' => 'integer',
            'break_minutes' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appliesOnWeekday(int $isoDow): bool
    {
        return in_array($isoDow, $this->working_days ?? [], true);
    }
}
