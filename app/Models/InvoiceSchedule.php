<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSchedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Contract\Contract;
use Carbon\{Carbon, CarbonInterface};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Abrechnungsplan für wiederkehrende Rechnungen (MVP-415). Der Scheduler
 * erzeugt AUSSCHLIESSLICH Entwürfe — Ausstellung und Versand bleiben
 * manuelle, auditierte Schritte.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int|null $contract_id
 * @property string $title
 * @property string $interval_unit
 * @property int $interval_count
 * @property string $billing_period_mode
 * @property Carbon $next_run_on
 * @property Carbon|null $last_run_on
 * @property Carbon|null $end_on
 * @property string $status
 * @property int|null $created_by
 */
class InvoiceSchedule extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ENDED = 'ended';

    public const UNIT_WEEK = 'week';

    public const UNIT_MONTH = 'month';

    public const UNIT_QUARTER = 'quarter';

    public const UNIT_YEAR = 'year';

    /** @var array<int, string> */
    public const UNITS = [self::UNIT_WEEK, self::UNIT_MONTH, self::UNIT_QUARTER, self::UNIT_YEAR];

    public const MODE_PREVIOUS = 'previous';

    public const MODE_CURRENT = 'current';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'contract_id',
        'title',
        'interval_unit',
        'interval_count',
        'billing_period_mode',
        'next_run_on',
        'last_run_on',
        'end_on',
        'status',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'interval_count' => 'integer',
        'next_run_on' => 'date',
        'last_run_on' => 'date',
        'end_on' => 'date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<InvoiceScheduleItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(InvoiceScheduleItem::class)->orderBy('position');
    }

    /** @return HasMany<InvoiceScheduleRun, $this> */
    public function runs(): HasMany {
        return $this->hasMany(InvoiceScheduleRun::class)->orderByDesc('period_start');
    }

    /** @param Builder<InvoiceSchedule> $query */
    public function scopeActive(Builder $query): void {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Abrechnungszeitraum des Laufs am Stichtag `$runOn` (Modus previous/current).
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function periodFor(CarbonInterface $runOn): array {
        $run = Carbon::parse($runOn->toDateString());
        if ($this->billing_period_mode === self::MODE_CURRENT) {
            $start = $run->copy();
            $end = $this->addInterval($run)->subDay();
        } else {
            $end = $run->copy()->subDay();
            $start = $this->subInterval($run);
        }

        return ['start' => $start, 'end' => $end];
    }

    /** Nächster Ausführungstag nach `$runOn`. */
    public function addInterval(CarbonInterface $runOn): Carbon {
        $run = Carbon::parse($runOn->toDateString());
        $count = max(1, (int) $this->interval_count);

        return match ($this->interval_unit) {
            self::UNIT_WEEK => $run->addWeeks($count),
            self::UNIT_QUARTER => $run->addMonthsNoOverflow(3 * $count),
            self::UNIT_YEAR => $run->addYearsNoOverflow($count),
            default => $run->addMonthsNoOverflow($count),
        };
    }

    private function subInterval(CarbonInterface $runOn): Carbon {
        $run = Carbon::parse($runOn->toDateString());
        $count = max(1, (int) $this->interval_count);

        return match ($this->interval_unit) {
            self::UNIT_WEEK => $run->subWeeks($count),
            self::UNIT_QUARTER => $run->subMonthsNoOverflow(3 * $count),
            self::UNIT_YEAR => $run->subYearsNoOverflow($count),
            default => $run->subMonthsNoOverflow($count),
        };
    }

    /**
     * Vorschau der nächsten Ausführungstage (für die UI).
     *
     * @return list<Carbon>
     */
    public function upcomingRuns(int $count = 3): array {
        if ($this->status !== self::STATUS_ACTIVE) {
            return [];
        }
        $runs = [];
        $cursor = $this->next_run_on->copy();
        for ($i = 0; $i < $count; $i++) {
            if ($this->end_on !== null && $cursor->greaterThan($this->end_on)) {
                break;
            }
            $runs[] = $cursor->copy();
            $cursor = $this->addInterval($cursor);
        }

        return $runs;
    }

    public function unitLabel(): string {
        return match ($this->interval_unit) {
            self::UNIT_WEEK => (string) __('Woche(n)'),
            self::UNIT_QUARTER => (string) __('Quartal(e)'),
            self::UNIT_YEAR => (string) __('Jahr(e)'),
            default => (string) __('Monat(e)'),
        };
    }

    public function statusLabel(): string {
        return match ($this->status) {
            self::STATUS_ACTIVE => (string) __('Aktiv'),
            self::STATUS_PAUSED => (string) __('Pausiert'),
            default => (string) __('Beendet'),
        };
    }

    public function statusTone(): string {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_PAUSED => 'warning',
            default => 'neutral',
        };
    }
}
