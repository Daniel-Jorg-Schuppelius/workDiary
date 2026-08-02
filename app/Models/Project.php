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

use App\Casts\MoneyCast;
use App\Enums\Diary\LocationMode;
use App\Enums\Project\ProjectStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, GeneratesUniqueSlug, HasCommunicationNotes, HasSqid, ResolvesEffectiveProjectSettings};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\{Carbon, Collection};

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $customer_id
 * @property int|null $foreign_customer_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $number
 * @property string|null $description
 * @property string|null $invoice_text
 * @property string|null $color
 * @property ProjectStatus $status
 * @property bool $is_default
 * @property bool $is_maintenance
 * @property LocationMode|null $default_location_mode
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $archived_at
 * @property int|null $created_by
 * @property \CommonToolkit\ValueObjects\Money|null $hourly_rate
 * @property \CommonToolkit\ValueObjects\Money|null $internal_rate
 * @property int|null $time_budget
 * @property \CommonToolkit\ValueObjects\Money|null $budget
 * @property string|null $budget_type
 * @property bool|null $billable
 * @property bool|null $weather_auto_fetch
 * @property bool $global_activities
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * Speicher-Seiteneffekte (Slug, Parent-/Kunden-/Status-Vererbung) liegen im
 * {@see \App\Observers\ProjectObserver}; effektive Einstellungen mit
 * Vererbung im Concern {@see ResolvesEffectiveProjectSettings}
 * (Refactoring Welle 2, B6b).
 */
class Project extends Model {
    use Auditable;
    use BelongsToOrganization;
    use GeneratesUniqueSlug;
    use HasCommunicationNotes;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;
    use ResolvesEffectiveProjectSettings;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'foreign_customer_id',
        'parent_id',
        'name',
        'slug',
        'number',
        'description',
        'invoice_text',
        'color',
        'status',
        'is_default',
        'is_maintenance',
        'default_location_mode',
        'starts_on',
        'ends_on',
        'archived_at',
        'created_by',
        'hourly_rate',
        'internal_rate',
        'time_budget',
        'budget',
        'budget_type',
        'billing_increment_minutes',
        'billing_grouping_gap_minutes',
        'billable',
        'weather_auto_fetch',
        'global_activities',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'archived_at' => 'datetime',
        'hourly_rate' => MoneyCast::class . ':currency,2',
        'internal_rate' => MoneyCast::class . ':currency,2',
        'budget' => MoneyCast::class . ':currency,2',
        'time_budget' => 'integer',
        'billing_increment_minutes' => 'integer',
        'billing_grouping_gap_minutes' => 'integer',
        'billable' => 'boolean',
        'weather_auto_fetch' => 'boolean',
        'global_activities' => 'boolean',
        'is_default' => 'boolean',
        'is_maintenance' => 'boolean',
        'status' => ProjectStatus::class,
        'default_location_mode' => LocationMode::class,
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder {
        return $query->where('is_default', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRegular(Builder $query): Builder {
        return $query->where('is_default', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRoots(Builder $query): Builder {
        return $query->whereNull('parent_id');
    }

    /**
     * Sprechende URLs: "<kunde-slug>/<projekt-slug>". Sub-Projekte ohne
     * eigenen Kunden (über den Parent geerbt) und interne Projekte ohne
     * Kunden landen unter dem Sentinel "intern".
     */
    public function getRouteKeyName(): string {
        return 'slug';
    }

    /**
     * Project nutzt Slug-Routing, daher liefert {@see getRouteKey()} den
     * zusammengesetzten Slug. Der `->sqid`-Accessor muss aber die echte,
     * opake Sqid liefern (API/JSON), nicht den Slug.
     */
    public function getSqidAttribute(): string {
        return app(\App\Services\SqidEncoder::class)->encode(static::class, (int) $this->getKey());
    }

    public function getRouteKey(): string {
        $customerSlug = $this->customer?->slug ?: 'intern';

        return $customerSlug . '/' . ((string) $this->slug);
    }

    /**
     * Akzeptiert:
     *  - numerische ID (Backward-Compat für API/Bookmarks)
     *  - zusammengesetzte URL "<kunde-slug>/<projekt-slug>" inkl. Sentinel "intern"
     *  - reinen Projekt-Slug (Fallback, sucht beim ersten Treffer)
     */
    public function resolveRouteBinding($value, $field = null): ?Model {
        $value = (string) $value;

        if (ctype_digit($value)) {
            return $this->newQuery()->whereKey((int) $value)->first();
        }

        if (str_contains($value, '/')) {
            [$customerPart, $projectPart] = explode('/', $value, 2);
            $query = $this->newQuery()->where('slug', $projectPart);
            if ($customerPart === 'intern') {
                $query->whereNull('customer_id');
            } else {
                $query->whereHas('customer', fn($q) => $q->where('slug', $customerPart));
            }

            return $query->first();
        }

        // Opake Sqid (API/JSON) vor dem Slug-Fallback versuchen. Kollision Sqid↔Slug praktisch ausgeschlossen:
        // decode() verlangt exakten Roundtrip (min_length 10); scheitert es, greift der Slug-Pfad.
        $sqidId = app(\App\Services\SqidEncoder::class)->decode(static::class, $value);
        if ($sqidId !== null) {
            return $this->newQuery()->whereKey($sqidId)->first();
        }

        return $this->newQuery()->where($field ?? 'slug', $value)->first();
    }

    public static function uniqueSlug(string $name, ?int $customerId = null, ?int $ignoreId = null): string {
        // Eindeutig je Kunde (innerhalb des BelongsToOrganization-Scopes).
        return self::resolveUniqueSlug($name, 'project', fn(string $slug): bool => static::query()
            ->where('customer_id', $customerId)
            ->where('slug', $slug)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists());
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ForeignCustomer, $this> */
    public function foreignCustomer(): BelongsTo {
        return $this->belongsTo(ForeignCustomer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function parent(): BelongsTo {
        return $this->belongsTo(Project::class, 'parent_id');
    }

    /** @return HasMany<Project, $this> */
    public function children(): HasMany {
        return $this->hasMany(Project::class, 'parent_id')->orderBy('name');
    }

    /**
     * Liefert alle Nachfahren rekursiv (kleine Bäume; ungeeignet für riesige Hierarchien).
     *
     * @return Collection<int, Project>
     */
    public function descendants(): Collection {
        $out = collect();
        foreach ($this->children()->get() as $child) {
            $out->push($child);
            $out = $out->merge($child->descendants());
        }

        return $out;
    }

    public function isAncestorOf(Project $other): bool {
        $cursor = $other->parent;
        while ($cursor !== null) {
            if ((int) $cursor->id === (int) $this->id) {
                return true;
            }
            $cursor = $cursor->parent;
        }

        return false;
    }

    /** @return HasMany<ProjectBillingRule, $this> */
    public function billingRules(): HasMany {
        return $this->hasMany(ProjectBillingRule::class);
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

    /**
     * Zugewiesene Arbeits-Teams (n:m). Aufträge können von mehreren Teams
     * gemeinsam bearbeitet werden.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Team, $this>
     */
    public function teams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany {
        return $this->belongsToMany(Team::class, 'project_team')->withTimestamps();
    }

    /**
     * Optionale Einzelmitglieder (zusätzlich zu den Team-Mitgliedern).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<User, $this>
     */
    public function members(): \Illuminate\Database\Eloquent\Relations\BelongsToMany {
        return $this->belongsToMany(User::class, 'project_user')->withTimestamps();
    }

    /**
     * Alle Personen, denen in diesem Projekt Aufgaben zugewiesen werden dürfen:
     * Vereinigung aus den Mitgliedern aller zugewiesenen Teams und den
     * Einzelmitgliedern, dedupliziert nach ID.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(): Collection {
        $this->loadMissing(['teams.members', 'members']);

        return $this->teams->flatMap(fn(Team $team) => $team->members)
            ->merge($this->members)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Projekte, an denen ein Benutzer beteiligt ist – über ein zugewiesenes
     * Team oder als Einzelmitglied. Für die „meine Aufträge"-Sicht.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, User $user): Builder {
        return $query->where(function (Builder $q) use ($user): void {
            $q->whereHas('teams.members', fn($m) => $m->whereKey($user->id))
                ->orWhereHas('members', fn($m) => $m->whereKey($user->id));
        });
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<Timesheet, $this> */
    public function timesheets(): HasMany {
        return $this->hasMany(Timesheet::class);
    }

    /**
     * Aktive Projekte hierarchisch für Picker-Dialoge (Zeiteintrag, Stundenzettel)
     * aufbereiten. Liefert eine Collection von Roots, eine Map childrenByParent
     * und eine Kunden-Liste mit Sentinel "0" für interne Projekte.
     *
     * @return array{roots: Collection<int, Project>, childrenByParent: Collection<int, Collection<int, Project>>, customers: Collection<int, array{id: int, name: string}>}
     */
    public static function pickerData(): array {
        $projects = self::query()
            ->where('status', ProjectStatus::Active)
            ->with('customer:id,name,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'customer_id', 'parent_id', 'color']);

        $byId = $projects->keyBy('id');

        /** @var array<int, Collection<int, Project>> $grouped */
        $grouped = [];
        foreach ($projects as $p) {
            $key = $p->parent_id ?? 0;
            $grouped[$key] ??= new Collection;
            $grouped[$key]->push($p);
        }
        $childrenByParent = new Collection($grouped);

        $roots = new Collection;
        foreach ($projects as $p) {
            if ($p->parent_id === null || ! $byId->has($p->parent_id)) {
                $roots->push($p);
            }
        }

        $customers = $projects
            ->map(fn(Project $p): array => $p->customer
                ? ['id' => (int) $p->customer->id, 'name' => (string) $p->customer->name]
                : ['id' => 0, 'name' => (string) __('Intern (ohne Kunde)')])
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->toBase();

        return [
            'roots' => $roots,
            'childrenByParent' => $childrenByParent,
            'customers' => $customers,
        ];
    }

    public function statusLabel(): string {
        return $this->status->label();
    }

    public function statusTone(): string {
        return $this->status->tone();
    }
}
