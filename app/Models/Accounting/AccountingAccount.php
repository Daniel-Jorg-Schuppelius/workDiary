<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\{AccountType, BalanceSide, EuerCategory};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Konto des Kontenplans (Feature 125, MVP-672).
 *
 * Ein Konto wird nie gelöscht, sobald darauf gebucht wurde — es wird
 * stillgelegt (`is_active = false`). Sonst hinge die Historie an einer
 * Nummer, die niemand mehr auflösen kann.
 *
 * @property AccountType $type
 * @property BalanceSide $normal_balance
 * @property ?EuerCategory $euer_category
 */
class AccountingAccount extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'number',
        'name',
        'type',
        'normal_balance',
        'is_open_item',
        'is_bank',
        'is_cash',
        'is_clearing',
        'euer_category',
        'deductible_percent',
        'default_tax_code_id',
        'datev_account',
        'is_active',
        'description',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'type' => AccountType::class,
        'normal_balance' => BalanceSide::class,
        'is_open_item' => 'boolean',
        'is_bank' => 'boolean',
        'is_cash' => 'boolean',
        'is_clearing' => 'boolean',
        'euer_category' => EuerCategory::class,
        'deductible_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<AccountingTaxCode, $this> */
    public function defaultTaxCode(): BelongsTo {
        return $this->belongsTo(AccountingTaxCode::class, 'default_tax_code_id');
    }

    /**
     * @param  Builder<AccountingAccount>  $query
     * @return Builder<AccountingAccount>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('is_active', true);
    }

    /**
     * Abziehbarer Anteil als Faktor — wirkt nur in der EÜR-Auswertung.
     */
    public function deductibleFactor(): float {
        $percent = (float) ($this->deductible_percent ?? 100);

        return max(0.0, min(100.0, $percent)) / 100;
    }

    public function displayLabel(): string {
        return $this->number . ' — ' . $this->name;
    }
}
