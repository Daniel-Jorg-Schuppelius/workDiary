<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingRecurringRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Enums\Finance\RecurringRunStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Ein Lauf einer wiederkehrenden Vorlage (Feature 125, MVP-675).
 *
 * Der Lauf ist der Vorgang, nicht sein Ergebnis: Bei einer Belegerwartung
 * bleibt er offen, bis das Original eintrifft — ein fingierter Beleg würde
 * die Lücke verstecken, statt sie zu zeigen.
 *
 * @property RecurringRunStatus $status
 * @property CurrencyCode $currency
 */
class AccountingRecurringRun extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'accounting_recurring_template_id',
        'period_key',
        'due_on',
        'status',
        'expected_amount',
        'currency',
        'accounting_entry_id',
        'fulfilled_by_type',
        'fulfilled_by_id',
        'fulfilled_at',
        'blocked_reason',
        'notified_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => RecurringRunStatus::class,
        'currency' => CurrencyCode::class,
        'expected_amount' => MoneyCast::class . ':currency,2',
        'due_on' => 'date',
        'fulfilled_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    /** @return BelongsTo<AccountingRecurringTemplate, $this> */
    public function template(): BelongsTo {
        return $this->belongsTo(AccountingRecurringTemplate::class, 'accounting_recurring_template_id');
    }

    /** @return BelongsTo<AccountingEntry, $this> */
    public function entry(): BelongsTo {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    /** @return MorphTo<Model, $this> */
    public function fulfilledBy(): MorphTo {
        return $this->morphTo();
    }

    /**
     * Offene Vorgänge, deren Fälligkeit vorbei ist — die Arbeitsliste des
     * Pakets.
     *
     * @param  Builder<AccountingRecurringRun>  $query
     * @return Builder<AccountingRecurringRun>
     */
    public function scopeOverdue(Builder $query, \Carbon\CarbonInterface $asOf): Builder {
        return $query->whereIn('status', [RecurringRunStatus::Expected->value, RecurringRunStatus::DraftCreated->value])
            ->whereDate('due_on', '<', $asOf->toDateString());
    }
}
