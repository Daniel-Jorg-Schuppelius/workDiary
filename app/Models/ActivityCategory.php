<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivityCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ActivityCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $key
 * @property string $label
 * @property string $activity_type
 * @property bool $billable_default
 * @property bool $counts_as_work
 * @property string|null $color
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ActivityCategory extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ActivityCategoryFactory> */
    use HasFactory;

    public const TYPE_ADMIN = 'admin';

    public const TYPE_TRAINING = 'training';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_TRAVEL = 'travel';

    public const TYPE_BREAK = 'break';

    public const TYPE_ABSENCE = 'absence';

    public const TYPE_STANDBY = 'standby';

    public const TYPE_OTHER = 'other';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_ADMIN,
        self::TYPE_TRAINING,
        self::TYPE_MEETING,
        self::TYPE_INTERNAL,
        self::TYPE_TRAVEL,
        self::TYPE_BREAK,
        self::TYPE_ABSENCE,
        self::TYPE_STANDBY,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'organization_id',
        'key',
        'label',
        'activity_type',
        'billable_default',
        'counts_as_work',
        'color',
        'icon',
        'sort_order',
        'active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'billable_default' => 'boolean',
            'counts_as_work' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * @param  Builder<ActivityCategory>  $q
     * @return Builder<ActivityCategory>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }
}
