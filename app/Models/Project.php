<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ARCHIVED = 'archived';

    /** @var array<int, string> */
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_PAUSED, self::STATUS_ARCHIVED];

    protected $fillable = [
        'organization_id',
        'customer_id',
        'name',
        'slug',
        'number',
        'description',
        'invoice_text',
        'color',
        'status',
        'starts_on',
        'ends_on',
        'archived_at',
        'created_by',
        'hourly_rate',
        'internal_rate',
        'time_budget',
        'budget',
        'budget_type',
        'billable',
        'global_activities',
    ];

    protected function casts(): array {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'archived_at' => 'datetime',
            'hourly_rate' => 'decimal:2',
            'internal_rate' => 'decimal:2',
            'budget' => 'decimal:2',
            'time_budget' => 'integer',
            'billable' => 'boolean',
            'global_activities' => 'boolean',
        ];
    }

    protected static function booted(): void {
        static::saving(function (Project $project): void {
            if (! $project->slug) {
                $project->slug = static::uniqueSlug($project->name);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $i = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    /** @return HasMany<Milestone, $this> */
    public function milestones(): HasMany {
        return $this->hasMany(Milestone::class)->orderBy('position')->orderBy('due_date');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<Timesheet, $this> */
    public function timesheets(): HasMany {
        return $this->hasMany(Timesheet::class);
    }

    public function statusLabel(): string {
        return match ($this->status) {
            self::STATUS_ACTIVE => __('Aktiv'),
            self::STATUS_PAUSED => __('Pausiert'),
            self::STATUS_ARCHIVED => __('Archiviert'),
            default => $this->status,
        };
    }

    public function statusTone(): string {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_PAUSED => 'warning',
            self::STATUS_ARCHIVED => 'ghost',
            default => 'ghost',
        };
    }
}
