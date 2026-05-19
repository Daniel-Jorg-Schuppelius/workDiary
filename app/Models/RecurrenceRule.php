<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $project_id
 * @property int|null $customer_id
 * @property int|null $entry_type_id
 * @property int|null $assigned_user_id
 * @property int|null $created_by
 * @property string $name
 * @property string|null $title_template
 * @property string $content_template
 * @property int|null $default_service_minutes
 * @property string|null $default_priority
 * @property string $default_location_mode
 * @property string $frequency
 * @property int $interval
 * @property string|null $byweekday
 * @property int|null $bymonthday
 * @property int|null $bymonth
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $last_generated_until
 * @property bool $is_active
 */
class RecurrenceRule extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
    use HasFactory;

    public const FREQ_DAILY = 'daily';

    public const FREQ_WEEKLY = 'weekly';

    public const FREQ_MONTHLY = 'monthly';

    public const FREQ_YEARLY = 'yearly';

    /** @var list<string> */
    public const FREQUENCIES = [
        self::FREQ_DAILY,
        self::FREQ_WEEKLY,
        self::FREQ_MONTHLY,
        self::FREQ_YEARLY,
    ];

    /** @var list<string> */
    public const WEEKDAY_CODES = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    protected $fillable = [
        'organization_id',
        'project_id',
        'customer_id',
        'entry_type_id',
        'assigned_user_id',
        'created_by',
        'name',
        'title_template',
        'content_template',
        'default_service_minutes',
        'default_priority',
        'default_location_mode',
        'frequency',
        'interval',
        'byweekday',
        'bymonthday',
        'bymonth',
        'starts_on',
        'ends_on',
        'last_generated_until',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'last_generated_until' => 'date',
        'is_active' => 'boolean',
        'interval' => 'integer',
        'default_service_minutes' => 'integer',
        'bymonthday' => 'integer',
        'bymonth' => 'integer',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo
    {
        return $this->belongsTo(EntryType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    /**
     * @return list<string> Liste der Wochentags-Codes (z.B. ['MO', 'WE']).
     */
    public function weekdays(): array
    {
        if (! $this->byweekday) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->byweekday))));
    }

    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            self::FREQ_DAILY => __('Täglich'),
            self::FREQ_WEEKLY => __('Wöchentlich'),
            self::FREQ_MONTHLY => __('Monatlich'),
            self::FREQ_YEARLY => __('Jährlich'),
            default => $this->frequency,
        };
    }
}
