<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Customer;

use App\Casts\PeriodKeyCast;
use App\Enums\Customer\CustomerDeadlineStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasJournal, HasSqid};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Support\Period\PeriodKey;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Database\Factories\Customer\CustomerDeadlineFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Frist eines Kunden je Regelsatz, Fristart und Periode (Feature 197,
 * MVP-1632). `original_due_on` ist der berechnete Termin, `due_on` der
 * geltende (verlängert oder vorangefordert). Der Jahreslauf rechnet nur offene,
 * nicht von Hand gesetzte Fristen neu; erledigte und entfallene bleiben.
 * Übergänge nur über {@see \App\Services\Customer\Deadlines\CustomerDeadlineService},
 * Verlauf im Journal `customer_deadline_events`.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int|null $customer_deadline_profile_id
 * @property string $rule_set
 * @property string $kind
 * @property PeriodKey $period_key
 * @property Carbon $due_on
 * @property Carbon $original_due_on
 * @property CustomerDeadlineStatus $status
 * @property bool $is_due_fixed
 * @property Carbon|null $advance_requested_on
 * @property string|null $extension_reason
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property int|null $diary_entry_id
 * @property int|null $responsible_user_id
 * @property bool $is_region_assumed
 * @property Carbon|null $warned_at
 * @property Carbon|null $overdue_warned_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerDeadline extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<CustomerDeadlineFactory> */
    use HasFactory;
    use HasJournal;
    use HasSqid;

    protected static string $journalClass = CustomerDeadlineEvent::class;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'customer_deadline_profile_id',
        'rule_set',
        'kind',
        'period_key',
        'due_on',
        'original_due_on',
        'status',
        'is_due_fixed',
        'advance_requested_on',
        'extension_reason',
        'completed_at',
        'completed_by',
        'diary_entry_id',
        'responsible_user_id',
        'is_region_assumed',
        'warned_at',
        'overdue_warned_at',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_key' => PeriodKeyCast::class,
        'due_on' => 'date',
        'original_due_on' => 'date',
        'status' => CustomerDeadlineStatus::class,
        'is_due_fixed' => 'boolean',
        'advance_requested_on' => 'date',
        'completed_at' => 'datetime',
        'is_region_assumed' => 'boolean',
        'warned_at' => 'datetime',
        'overdue_warned_at' => 'datetime',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerDeadlineProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(CustomerDeadlineProfile::class, 'customer_deadline_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completer(): BelongsTo {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /**
     * Noch zu erledigen (offen oder verlängert).
     *
     * @param  Builder<CustomerDeadline>  $query
     */
    public function scopeOpen(Builder $query): void {
        $query->whereIn('status', CustomerDeadlineStatus::openValues());
    }

    /**
     * Fällig bis einschließlich $day.
     *
     * @param  Builder<CustomerDeadline>  $query
     */
    public function scopeDueUntil(Builder $query, CarbonInterface $day): void {
        $query->where('due_on', '<', DateRange::dayAfter($day));
    }

    /** Offen und der Termin liegt vor $today. */
    public function isOverdue(CarbonInterface $today): bool {
        return $this->status->isOpen() && $this->due_on->toDateString() < $today->toDateString();
    }

    /**
     * Ampel der Übersicht: überfällig `error`, innerhalb der Vorwarnung `warning`,
     * später `success`; erledigt bzw. entfallen ohne Ampel.
     */
    public function trafficLight(CarbonInterface $today, int $warnDays): ?string {
        if (! $this->status->isOpen()) {
            return null;
        }
        $due = $this->due_on->toDateString();

        return match (true) {
            $due < $today->toDateString() => 'error',
            $due <= Carbon::instance($today)->addDays(max(0, $warnDays))->toDateString() => 'warning',
            default => 'success',
        };
    }
}
