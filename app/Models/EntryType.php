<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Diary\Priority;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EntryTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $slug
 * @property string $label
 * @property string $icon
 * @property string $color
 * @property string|null $description
 * @property int $sort
 * @property bool $is_active
 * @property bool $requires_customer
 * @property bool $requires_address
 * @property bool $requires_schedule
 * @property bool $requires_tour
 * @property bool $allow_priority
 * @property bool $allow_tour
 * @property int $default_status
 * @property int|null $default_service_minutes
 * @property Priority|null $default_priority
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EntryType extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<EntryTypeFactory> */
    use HasFactory;

    public const SLUG_GENERAL = 'general';

    public const SLUG_SERVICE = 'service';

    public const SLUG_CARE_VISIT = 'care_visit';

    public const SLUG_IT_TICKET = 'it_ticket';

    public const SLUG_HVAC_JOB = 'hvac_job';

    protected $fillable = [
        'organization_id',
        'slug',
        'label',
        'icon',
        'color',
        'description',
        'sort',
        'is_active',
        'requires_customer',
        'requires_address',
        'requires_schedule',
        'requires_tour',
        'allow_priority',
        'allow_tour',
        'default_status',
        'default_service_minutes',
        'default_priority',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
        'requires_customer' => 'boolean',
        'requires_address' => 'boolean',
        'requires_schedule' => 'boolean',
        'requires_tour' => 'boolean',
        'allow_priority' => 'boolean',
        'allow_tour' => 'boolean',
        'sort' => 'integer',
        'default_status' => 'integer',
        'default_service_minutes' => 'integer',
        'default_priority' => Priority::class,
    ];

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    /**
     * @param  Builder<EntryType>  $query
     * @return Builder<EntryType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<EntryType>  $query
     * @return Builder<EntryType>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('label');
    }

    /** Flag-Sammlung als Array fürs Frontend (Alpine x-data). */
    /** @return array<string, bool|int|string|null> */
    public function flagsArray(): array
    {
        return [
            'requires_customer' => $this->requires_customer,
            'requires_address' => $this->requires_address,
            'requires_schedule' => $this->requires_schedule,
            'requires_tour' => $this->requires_tour,
            'allow_priority' => $this->allow_priority,
            'allow_tour' => $this->allow_tour || $this->requires_tour,
            'default_service_minutes' => $this->default_service_minutes,
            'default_priority' => $this->default_priority?->value,
            'default_status' => $this->default_status,
        ];
    }
}
