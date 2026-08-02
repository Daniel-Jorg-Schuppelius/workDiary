<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TimeEntry\{TimeEntryActivityType, TimeEntryKind};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid, HasTags};
use App\Services\RateCalculator;
use App\Support\Formats;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphMany};
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $project_id
 * @property int|null $timesheet_id
 * @property int|null $task_id
 * @property int|null $diary_entry_id
 * @property int|null $user_id
 * @property int|null $activity_category_id
 * @property int|null $attendance_id
 * @property int|null $travel_log_id
 * @property Carbon|null $date
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int $break_minutes
 * @property TimeEntryKind $kind
 * @property TimeEntryActivityType $activity_type
 * @property int $minutes
 * @property string|null $description
 * @property bool $billable
 * @property Money|null $hourly_rate
 * @property Money|null $fixed_rate
 * @property Money|null $rate
 * @property Money|null $internal_rate
 * @property bool $exported
 * @property int|null $customer_billing_rate_id
 * @property int $billing_travel_minutes
 * @property bool $billing_travel_manual
 */
class TimeEntry extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    use HasSqid;
    use HasTags;

    /**
     * Liefert ein lokalisiertes Label für einen activity_type-Wert.
     * Akzeptiert sowohl Enum-Cases als auch String-Slugs (Backwards-Compat
     * für Blade-Views, die Raw-Werte aus Aggregaten verarbeiten).
     */
    public static function activityLabel(TimeEntryActivityType|string|null $type): string {
        if ($type instanceof TimeEntryActivityType) {
            return $type->label();
        }
        $value = (string) $type;
        if ($value === '') {
            return (string) __('Unbekannt');
        }
        $enum = TimeEntryActivityType::tryFrom($value);

        return $enum?->label() ?? ucfirst($value);
    }

    // High-level distribution category. When ACTIVITY_PROJECT, project_id
    // must be set. Other values use activity_category_id for reporting.
    /**
     * Nacharbeitsgrund (Rang 59, Domäne rework_reason).
     *
     * @return BelongsTo<\App\Models\Classification, $this>
     */
    public function reworkReason(): BelongsTo {
        return $this->belongsTo(\App\Models\Classification::class, 'rework_reason_classification_id');
    }

    /**
     * Kulanzgrund (Rang 59, Domäne goodwill_reason).
     *
     * @return BelongsTo<\App\Models\Classification, $this>
     */
    public function goodwillReason(): BelongsTo {
        return $this->belongsTo(\App\Models\Classification::class, 'goodwill_reason_classification_id');
    }

    protected $fillable = [
        'organization_id',
        'project_id',
        'timesheet_id',
        'task_id',
        'diary_entry_id',
        'user_id',
        'activity_category_id',
        'rework_reason_classification_id',
        'goodwill_reason_classification_id',
        'attendance_id',
        'travel_log_id',
        'manufacturing_order_id',
        'date',
        'started_at',
        'ended_at',
        'break_minutes',
        'kind',
        'activity_type',
        'minutes',
        'description',
        'billable',
        'hourly_rate',
        'fixed_rate',
        'rate',
        'internal_rate',
        'exported',
        'customer_billing_rate_id',
        'billing_travel_minutes',
        'billing_travel_manual',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'minutes' => 'integer',
        'break_minutes' => 'integer',
        'billable' => 'boolean',
        'exported' => 'boolean',
        'billing_travel_minutes' => 'integer',
        'billing_travel_manual' => 'boolean',
        'hourly_rate' => MoneyCast::class . ':currency,2',
        'fixed_rate' => MoneyCast::class . ':currency,2',
        'rate' => MoneyCast::class . ':currency,2',
        'internal_rate' => MoneyCast::class . ':currency,2',
        'kind' => TimeEntryKind::class,
        'activity_type' => TimeEntryActivityType::class,
    ];

    /**
     * Wurde der Stundensatz manuell überschrieben?
     *
     * Nicht über isDirty(): der Money-Cast schreibt die kanonische Form
     * ("16.50") zurück, während die Spalte je nach Treiber "16.5" liefert —
     * das Attribut gälte damit bei jedem Speichern als geändert und jeder
     * Beleg verlöre seinen Konditions-Bezug.
     */
    public function hourlyRateWasOverridden(): bool {
        if (! $this->isDirty('hourly_rate')) {
            return false;
        }

        // getRawOriginal: getOriginal() liefert bei Casts das Value Object zurück.
        $original = $this->getRawOriginal('hourly_rate');
        if ($original === null || $original === '' || $this->hourly_rate === null) {
            return ($original === null || $original === '') !== ($this->hourly_rate === null);
        }

        $originalAmount = (string) $original;

        return ! is_numeric($originalAmount)
            || bccomp($this->hourly_rate->getAmount(), $originalAmount, 4) !== 0;
    }

    /**
     * Trägt der Eintrag exakt den Satz der Kondition, auf die sein Marker
     * zeigt? Unterscheidet die Neubewertung nach einer Satzänderung von einem
     * echten Handeingriff. Der Resolver ist request-gecacht, kostet also keine
     * zusätzliche Abfrage.
     */
    public function matchesAgreementRate(): bool {
        if ($this->customer_billing_rate_id === null || $this->hourly_rate === null) {
            return false;
        }

        $rate = app(\App\Services\Billing\AgreementRateResolver::class)->rateFor($this);

        return $rate?->id === $this->customer_billing_rate_id
            && $rate->hourly_rate?->getAmount() === $this->hourly_rate->getAmount();
    }

    protected static function booted(): void {
        static::saving(function (TimeEntry $entry): void {
            // Default activity_type from kind / project presence.
            if (empty($entry->activity_type)) {
                $entry->activity_type = match (true) {
                    $entry->project_id !== null => TimeEntryActivityType::Project,
                    $entry->kind === TimeEntryKind::Travel => TimeEntryActivityType::Travel,
                    $entry->kind === TimeEntryKind::Standby => TimeEntryActivityType::Standby,
                    default => TimeEntryActivityType::Admin,
                };
            }

            // Enforce: project_id is required when activity_type=project.
            if ($entry->activity_type === TimeEntryActivityType::Project && $entry->project_id === null) {
                throw new \InvalidArgumentException(
                    'TimeEntry with activity_type=project requires a project_id.'
                );
            }

            if ($entry->started_at && $entry->ended_at) {
                $diff = (int) $entry->started_at->diffInMinutes($entry->ended_at, false);
                $diff = max(0, $diff - (int) ($entry->break_minutes ?? 0));
                $entry->minutes = $diff;
                if (! $entry->date) {
                    // Kalendertag in der Anzeige-Zeitzone, nicht UTC (wie Attendance): sonst zählt ein Eintrag um 00:30
                    // lokal zum Vortag (Gleitzeit/Tagesabschluss/Monatsrechnung). started_at bleibt UTC.
                    $entry->date = $entry->started_at->copy()->setTimezone(\App\Support\Tz::current())->startOfDay();
                }
            }

            // Ohne explizites billable erbt ein neuer Eintrag die effektive
            // Projekt-Einstellung (Parent-Kette → Kunde). Muss vor der
            // Snapshot-Berechnung stehen: ein fehlendes Attribut zählte dort
            // sonst als nicht abrechenbar (rate = 0), obwohl das DB-Default
            // true ist.
            if (! $entry->exists && ! array_key_exists('billable', $entry->getAttributes())) {
                $entry->billable = $entry->project?->effectiveBillable() ?? true;
            }

            // Recalculate billing snapshot whenever a relevant field changes.
            // date/started_at/activity_category_id gehören dazu, weil Kunden-
            // konditionen (Feature 098) Tagtyp- und Kategorie-abhängig sind.
            if ($entry->isDirty([
                'minutes',
                'billable',
                'hourly_rate',
                'fixed_rate',
                'project_id',
                'task_id',
                'user_id',
                'date',
                'started_at',
                'activity_category_id',
                'billing_travel_manual',
            ]) || ! $entry->exists) {
                if (
                    $entry->hourlyRateWasOverridden()
                    && $entry->hourly_rate !== null
                    && ! $entry->matchesAgreementRate()
                ) {
                    // Manueller Satz-Override löst den Konditions-Marker ab (E2)
                    // — nicht aber eine Satzpflege, die denselben Konditions-
                    // satz neu einträgt (reapplyRates); sonst verlöre jede
                    // Satzänderung ihren Nachweis.
                    $entry->customer_billing_rate_id = null;
                } elseif (
                    $entry->customer_billing_rate_id !== null
                    && $entry->isDirty(['date', 'started_at', 'activity_category_id', 'project_id'])
                ) {
                    // Konditions-Snapshot neu auflösen: Tagtyp (Sa→So), Kategorie
                    // oder Kunde können sich geändert haben. Manuelle Overrides
                    // (FK=NULL) bleiben unangetastet.
                    $entry->hourly_rate = null;
                    $entry->customer_billing_rate_id = null;
                }
                $entry->applyRateSnapshot();
            }
        });
    }

    /**
     * Rechnet den Abrechnungs-Snapshot (rate/internal_rate/hourly_rate sowie
     * die pauschale Anfahrt) aus der Satzhierarchie neu. Aus dem saving-Hook
     * heraus aufgerufen — und aus
     * {@see \App\Services\Billing\CustomerAccountStatementService::reapplyRates()},
     * das Bestandseinträge nachbewertet, bei denen kein Feld „dirty" wird.
     */
    public function applyRateSnapshot(): void {
        $result = app(RateCalculator::class)->compute($this);
        $currency = CurrencyCode::Euro;

        $this->rate = Money::ofFloat($result['rate'], $currency);
        $this->internal_rate = Money::ofFloat($result['internal_rate'], $currency);
        $this->billing_travel_minutes = $result['travel_minutes'];

        if ($this->hourly_rate === null && $result['hourly_rate'] !== null) {
            // Snapshot resolved hourly rate so historical entries stay stable.
            $this->hourly_rate = Money::ofFloat($result['hourly_rate'], $currency);
            $this->customer_billing_rate_id = $result['agreement_rate_id'];
        }
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /**
     * Angewendeter Sonderkonditions-Satz (Feature 098); NULL = normale
     * Hierarchie oder manueller Override.
     *
     * @return BelongsTo<\App\Models\Billing\CustomerBillingRate, $this>
     */
    public function customerBillingRate(): BelongsTo {
        return $this->belongsTo(\App\Models\Billing\CustomerBillingRate::class);
    }

    /** @return BelongsTo<Timesheet, $this> */
    public function timesheet(): BelongsTo {
        return $this->belongsTo(Timesheet::class);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('created_at');
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ActivityCategory, $this> */
    public function activityCategory(): BelongsTo {
        return $this->belongsTo(ActivityCategory::class);
    }

    /** @return BelongsTo<Attendance, $this> */
    public function attendance(): BelongsTo {
        return $this->belongsTo(Attendance::class);
    }

    /** @return BelongsTo<TravelLog, $this> */
    public function travelLog(): BelongsTo {
        return $this->belongsTo(TravelLog::class);
    }

    public function isProjectWork(): bool {
        return $this->activity_type === TimeEntryActivityType::Project;
    }

    public function hoursFormatted(): string {
        return Formats::duration((int) $this->minutes, 'clock');
    }
}
