<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Project.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $customer_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $number
 * @property string|null $description
 * @property string|null $invoice_text
 * @property string|null $color
 * @property string $status
 * @property bool $is_default
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 * @property string|null $hourly_rate
 * @property string|null $internal_rate
 * @property int|null $time_budget
 * @property string|null $budget
 * @property string|null $budget_type
 * @property bool|null $billable
 * @property bool $global_activities
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Project extends Model
{
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
        'parent_id',
        'name',
        'slug',
        'number',
        'description',
        'invoice_text',
        'color',
        'status',
        'is_default',
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

    protected function casts(): array
    {
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
            'is_default' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('is_default', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            if ($project->parent_id !== null) {
                $parent = static::query()->find($project->parent_id);
                if ($parent === null) {
                    throw new \InvalidArgumentException('Parent-Projekt existiert nicht.');
                }
                if ($project->exists && (int) $parent->id === (int) $project->id) {
                    throw new \InvalidArgumentException('Ein Projekt kann nicht sein eigenes Übergeordnetes Projekt sein.');
                }
                if ($project->exists && $project->isAncestorOf($parent)) {
                    throw new \InvalidArgumentException('Zyklus erkannt: das gewählte Parent-Projekt ist ein Sub-Projekt dieses Projekts.');
                }
                // Sub-Projekte erben den Customer vom Parent.
                $project->customer_id = $parent->customer_id;
                // Sub-Projekte dürfen kein Standardprojekt sein.
                $project->is_default = false;
            }

            if (! $project->slug) {
                $project->slug = static::uniqueSlug($project->name);
            }
        });

        // Sicherstellen, dass pro Kunde höchstens ein Standardprojekt existiert.
        static::saved(function (Project $project): void {
            if ($project->wasChanged('customer_id')) {
                $newCustomerId = $project->customer_id;

                // Sub-Projekte erben den neuen Kunden mit (rekursiv via Events).
                static::query()
                    ->where('parent_id', $project->id)
                    ->where(function (Builder $q) use ($newCustomerId): void {
                        $q->where('customer_id', '!=', $newCustomerId)
                            ->orWhereNull('customer_id');
                    })
                    ->get()
                    ->each(function (Project $child) use ($newCustomerId): void {
                        $child->customer_id = $newCustomerId;
                        $child->save();
                    });

                // DiaryEntries mitziehen, außer abgeschlossene (STATUS_DONE).
                DiaryEntry::query()
                    ->where('project_id', $project->id)
                    ->where('status', '!=', DiaryEntry::STATUS_DONE)
                    ->update(['customer_id' => $newCustomerId]);

                // Rechnungen mitziehen, außer freigegebene/bezahlte/stornierte (nur DRAFT).
                Invoice::query()
                    ->where('project_id', $project->id)
                    ->where('status', Invoice::STATUS_DRAFT)
                    ->update(['customer_id' => $newCustomerId]);
            }

            if ($project->wasChanged('status')) {
                $newStatus = $project->status;

                // Sub-Projekte erben den Status rekursiv mit (Events feuern auch für deren Children).
                static::query()
                    ->where('parent_id', $project->id)
                    ->where('status', '!=', $newStatus)
                    ->get()
                    ->each(function (Project $child) use ($newStatus): void {
                        $child->status = $newStatus;
                        $child->save();
                    });
            }

            if (! $project->is_default || $project->customer_id === null) {
                return;
            }
            static::query()
                ->where('customer_id', $project->customer_id)
                ->where('id', '!=', $project->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $i = 2;
        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'parent_id');
    }

    /** @return HasMany<Project, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Project::class, 'parent_id')->orderBy('name');
    }

    /**
     * Liefert alle Nachfahren rekursiv (kleine Bäume; ungeeignet für riesige Hierarchien).
     *
     * @return Collection<int, Project>
     */
    public function descendants(): Collection
    {
        $out = collect();
        foreach ($this->children()->get() as $child) {
            $out->push($child);
            $out = $out->merge($child->descendants());
        }

        return $out;
    }

    public function isAncestorOf(Project $other): bool
    {
        $cursor = $other->parent;
        while ($cursor !== null) {
            if ((int) $cursor->id === (int) $this->id) {
                return true;
            }
            $cursor = $cursor->parent;
        }

        return false;
    }

    /**
     * Stundensatz mit Vererbung: eigener Wert > Parent (rekursiv) > Customer.
     */
    public function effectiveHourlyRate(): ?float
    {
        if ($this->hourly_rate !== null) {
            return (float) $this->hourly_rate;
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveHourlyRate();
        }

        return $this->customer?->hourly_rate !== null ? (float) $this->customer->hourly_rate : null;
    }

    public function effectiveInternalRate(): ?float
    {
        if ($this->internal_rate !== null) {
            return (float) $this->internal_rate;
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveInternalRate();
        }

        return $this->customer?->internal_rate !== null ? (float) $this->customer->internal_rate : null;
    }

    public function effectiveBillable(): bool
    {
        if ($this->billable !== null) {
            return (bool) $this->billable;
        }
        if ($this->parent !== null) {
            return $this->parent->effectiveBillable();
        }

        return (bool) ($this->customer->billable ?? true);
    }

    /** @return HasMany<ProjectBillingRule, $this> */
    public function billingRules(): HasMany
    {
        return $this->hasMany(ProjectBillingRule::class);
    }

    /**
     * Liefert die passendste Billing-Regel für ein Kind (kind-Match vor Fallback,
     * höchste priority). Fällt rekursiv auf Parent-Projekt zurück.
     */
    public function resolveBillingRule(?string $kind, string $plugin = 'lexoffice'): ?ProjectBillingRule
    {
        $rule = $this->billingRules()
            ->where('plugin_id', $plugin)
            ->forKind($kind)
            ->first();
        if ($rule !== null) {
            return $rule;
        }

        return $this->parent?->resolveBillingRule($kind, $plugin);
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    /** @return HasMany<Milestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position')->orderBy('due_date');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<Timesheet, $this> */
    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => __('Aktiv'),
            self::STATUS_PAUSED => __('Pausiert'),
            self::STATUS_ARCHIVED => __('Archiviert'),
            default => $this->status,
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_PAUSED => 'warning',
            self::STATUS_ARCHIVED => 'ghost',
            default => 'ghost',
        };
    }
}
