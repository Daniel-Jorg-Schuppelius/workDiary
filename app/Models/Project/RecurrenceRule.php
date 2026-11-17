<?php
/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Project;

use App\Enums\Diary\{LocationMode, Priority};
use App\Enums\Period\PeriodKind;
use App\Enums\Recurrence\RecurrenceFrequency;
use App\Models\Classification\EntryType;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Support\Period\PeriodKey;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $project_id
 * @property int|null $customer_id
 * @property int|null $entry_type_id
 * @property int|null $assigned_user_id
 * @property int|null $created_by
 * @property string $name
 * @property string|null $title_template
 * @property string $content_template
 * @property int|null $default_service_minutes
 * @property Priority|null $default_priority
 * @property LocationMode $default_location_mode
 * @property RecurrenceFrequency $frequency
 * @property int $interval
 * @property string|null $byweekday
 * @property int|null $bymonthday
 * @property int|null $bymonth
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $last_generated_until
 * @property bool $is_active
 */
class RecurrenceRule extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** @var list<string> */
    public const array WEEKDAY_CODES = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

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
        'default_priority' => Priority::class,
        'default_location_mode' => LocationMode::class,
        'frequency' => RecurrenceFrequency::class,
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo {
        return $this->belongsTo(EntryType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DiaryEntry, $this> */
    public function diaryEntries(): HasMany {
        return $this->hasMany(DiaryEntry::class);
    }

    /**
     * @return list<string> Liste der Wochentags-Codes (z.B. ['MO', 'WE']).
     */
    public function weekdays(): array {
        if (! $this->byweekday) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->byweekday))));
    }

    public function frequencyLabel(): string {
        return $this->frequency->label();
    }

    /**
     * Reihe der Merker für die nächste Periode (Feature 201, MVP-1646): die
     * Regel selbst; ihre Aufträge sind die Vorkommen. Nur Monats- und
     * Jahresregeln — Tage und Wochen kennt die Periode nicht, ein Merker „ab
     * nächstem Monat“ träfe bei einer Wochenregel nicht das nächste Mal.
     */
    public function carryForwardSeries(): ?string {
        return $this->carryForwardKind() !== null ? 'recurrence_rule:' . (int) $this->id : null;
    }

    /** Periode eines Vorkommens am Tag (Monatsregel alle 3 Monate → Quartal, Jahresregel → Jahr). */
    public function carryForwardPeriodAt(DateTimeInterface $date): ?PeriodKey {
        $kind = $this->carryForwardKind();

        return $kind !== null ? PeriodKey::containing($kind, $date) : null;
    }

    private function carryForwardKind(): ?PeriodKind {
        return match ($this->frequency) {
            RecurrenceFrequency::Monthly => PeriodKind::forMonths(max(1, (int) $this->interval)),
            RecurrenceFrequency::Yearly => PeriodKind::Year,
            RecurrenceFrequency::Daily, RecurrenceFrequency::Weekly => null,
        };
    }
}
