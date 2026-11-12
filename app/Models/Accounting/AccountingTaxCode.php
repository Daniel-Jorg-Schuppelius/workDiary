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
use App\Enums\Invoicing\IncomingInvoiceTaxCase;
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
 * Steuerschuld des Empfängers (MVP-1621): Richtung `both` (SelfAssessed),
 * Umsatzsteuer auf `tax_account_id`, Vorsteuer auf `input_tax_account_id`;
 * `tax_case` nennt den Steuerfall des Rechnungseingangs, für den es gilt.
 *
 * @property TaxCodeDirection $direction
 * @property IncomingInvoiceTaxCase|null $tax_case
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
        'tax_case',
        'tax_account_id',
        'input_tax_account_id',
        'ustva_base_field',
        'ustva_tax_field',
        'ustva_input_tax_field',
        'datev_tax_key',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'direction' => TaxCodeDirection::class,
        'tax_case' => IncomingInvoiceTaxCase::class,
        'rate' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<AccountingAccount, $this> */
    public function taxAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'tax_account_id');
    }

    /** @return BelongsTo<AccountingAccount, $this> Vorsteuerkonto bei Steuerschuld des Empfängers */
    public function inputTaxAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'input_tax_account_id');
    }

    public function isSelfAssessed(): bool {
        return $this->direction === TaxCodeDirection::SelfAssessed;
    }

    /** Liegt die Zeile auf einem Steuerkonto dieses Kennzeichens (Umsatz- oder Vorsteuer)? */
    public function isTaxAccount(?int $accountId): bool {
        return $accountId !== null && in_array($accountId, array_map(intval(...), array_filter([$this->tax_account_id, $this->input_tax_account_id])), true);
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
