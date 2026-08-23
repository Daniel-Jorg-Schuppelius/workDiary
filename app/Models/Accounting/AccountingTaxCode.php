<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingTaxCode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\TaxCodeDirection;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Steuerkennzeichen der Buchhaltung (Feature 125, MVP-672).
 *
 * Es ordnet das **eingefrorene** steuerliche Ergebnis eines Belegs
 * ({@see \App\Services\Invoicing\TaxResolver}) einem Steuerkonto zu — es
 * entscheidet keinen Einzelfall neu. Stichtagsfähig, damit ein
 * Satzwechsel Altbuchungen nicht rückwirkend verändert.
 *
 * @property TaxCodeDirection $direction
 */
class AccountingTaxCode extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'direction',
        'rate',
        'tax_category',
        'tax_account_id',
        'ustva_base_field',
        'ustva_tax_field',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'direction' => TaxCodeDirection::class,
        'rate' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<AccountingAccount, $this> */
    public function taxAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'tax_account_id');
    }

    /**
     * Am Stichtag gültige Kennzeichen.
     *
     * @param  Builder<AccountingTaxCode>  $query
     * @return Builder<AccountingTaxCode>
     */
    public function scopeValidOn(Builder $query, CarbonInterface $date): Builder {
        return $query->where('is_active', true)
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->where(function (Builder $inner) use ($date): void {
                $inner->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date->toDateString());
            });
    }
}
