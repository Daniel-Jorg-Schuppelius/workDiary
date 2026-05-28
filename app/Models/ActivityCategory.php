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

use App\Enums\Activity\ActivityCategoryType;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Database\Factories\ActivityCategoryFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $key
 * @property string $label
 * @property ActivityCategoryType $activity_type
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
class ActivityCategory extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<ActivityCategoryFactory> */
    use HasFactory;

    use HasSqid;

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

    /** @var array<string, string> */
    protected $casts = [
        'billable_default' => 'boolean',
        'counts_as_work' => 'boolean',
        'active' => 'boolean',
        'sort_order' => 'integer',
        'activity_type' => ActivityCategoryType::class,
    ];

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * @param  Builder<ActivityCategory>  $q
     * @return Builder<ActivityCategory>
     */
    public function scopeActive(Builder $q): Builder {
        return $q->where('active', true);
    }
}
