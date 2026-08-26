<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingBudget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{CostCenter, User};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Planwert je Konto und Geschäftsjahr (Feature 142, MVP-709).
 *
 * `month` 0 ist der Jahreswert, 1–12 der Kalendermonat; ein Konto führt je
 * Kostenstelle entweder einen Jahreswert oder Monatswerte, nie beides
 * ({@see \App\Services\Accounting\AccountingBudgetService::save()}).
 * Vorzeichen: positiv = erwarteter Ertrag bzw. erwarteter Aufwand — die
 * Kontoart entscheidet, die BWA dreht beim Einsortieren.
 *
 * `cost_center_key` spiegelt `cost_center_id` (0 = ohne) für den Unique-
 * Index — MySQL zählt NULL dort als verschieden (Begründung in der
 * Migration `2027_02_19_102500`).
 *
 * @property int $fiscal_year
 * @property int $month
 * @property ?int $cost_center_id
 * @property CurrencyCode $currency
 * @property \CommonToolkit\ValueObjects\Money $amount
 */
class AccountingBudget extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'fiscal_year',
        'accounting_account_id',
        'cost_center_id',
        'month',
        'amount',
        'currency',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'fiscal_year' => 'integer',
        'month' => 'integer',
        'currency' => CurrencyCode::class,
        'amount' => MoneyCast::class . ':currency,2',
    ];

    protected static function booted(): void {
        static::saving(function (self $budget): void {
            $budget->setAttribute('cost_center_key', (int) ($budget->cost_center_id ?? 0));
        });
    }

    /** @return BelongsTo<AccountingAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    /** @return BelongsTo<CostCenter, $this> */
    public function costCenter(): BelongsTo {
        return $this->belongsTo(CostCenter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
