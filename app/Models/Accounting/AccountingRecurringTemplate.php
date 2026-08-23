<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingRecurringTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Enums\Finance\{RecurringInterval, RecurringTemplateKind, RecurringTemplateStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Supplier, User};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Wiederkehrende Belegerwartung oder Buchungsvorlage (Feature 125, MVP-675).
 *
 * @property RecurringTemplateKind $kind
 * @property RecurringInterval $interval
 * @property RecurringTemplateStatus $status
 * @property CurrencyCode $currency
 * @property array<int, array<string, mixed>>|null $template_lines
 */
class AccountingRecurringTemplate extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'kind',
        'name',
        'interval',
        'due_day',
        'starts_on',
        'ends_on',
        'next_due_on',
        'status',
        'version',
        'expected_amount',
        'currency',
        'supplier_id',
        'template_lines',
        'responsible_user_id',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RecurringTemplateKind::class,
        'interval' => RecurringInterval::class,
        'status' => RecurringTemplateStatus::class,
        'currency' => CurrencyCode::class,
        'expected_amount' => MoneyCast::class . ':currency,2',
        'template_lines' => 'array',
        'due_day' => 'integer',
        'version' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'next_due_on' => 'date',
    ];

    /** @return HasMany<AccountingRecurringRun, $this> */
    public function runs(): HasMany {
        return $this->hasMany(AccountingRecurringRun::class, 'accounting_recurring_template_id')->orderByDesc('due_on');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @param  Builder<AccountingRecurringTemplate>  $query
     * @return Builder<AccountingRecurringTemplate>
     */
    public function scopeRunnable(Builder $query): Builder {
        return $query->where('status', RecurringTemplateStatus::Active->value);
    }

    /** Läuft die Vorlage an diesem Tag (fällig, gestartet, nicht beendet)? */
    public function isDueOn(\Carbon\CarbonInterface $date): bool {
        if (! $this->status->runs() || $this->next_due_on === null) {
            return false;
        }

        if ($this->starts_on->startOfDay()->greaterThan($date)) {
            return false;
        }

        if ($this->ends_on !== null && $this->ends_on->startOfDay()->lessThan($this->next_due_on->startOfDay())) {
            return false;
        }

        return $this->next_due_on->startOfDay()->lessThanOrEqualTo($date->copy()->startOfDay());
    }
}
