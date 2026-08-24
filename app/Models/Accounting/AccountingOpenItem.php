<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingOpenItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Enums\Finance\{OpenItemDirection, OpenItemStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};

/**
 * Offener Posten (Feature 125, MVP-674) — kontrollierte Projektion der
 * Festbuchung, nicht deren Ersatz.
 *
 * @property OpenItemDirection $direction
 * @property OpenItemStatus $status
 * @property CurrencyCode $currency
 */
class AccountingOpenItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'accounting_entry_id',
        'accounting_entry_line_id',
        'accounting_account_id',
        'direction',
        'status',
        'counterparty_type',
        'counterparty_id',
        'source_type',
        'source_id',
        'document_reference',
        'document_date',
        'due_date',
        'currency',
        'original_amount',
        'open_amount',
        'settled_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'direction' => OpenItemDirection::class,
        'status' => OpenItemStatus::class,
        'currency' => CurrencyCode::class,
        'original_amount' => MoneyCast::class . ':currency,2',
        'open_amount' => MoneyCast::class . ':currency,2',
        'document_date' => 'date',
        'due_date' => 'date',
        'settled_at' => 'datetime',
    ];

    /** @return BelongsTo<AccountingEntry, $this> */
    public function entry(): BelongsTo {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    /** @return BelongsTo<AccountingAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    /** @return HasMany<AccountingOpenItemSettlement, $this> */
    public function settlements(): HasMany {
        return $this->hasMany(AccountingOpenItemSettlement::class, 'accounting_open_item_id')->orderBy('id');
    }

    /** @return MorphTo<Model, $this> */
    public function counterparty(): MorphTo {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }

    /**
     * @param  Builder<AccountingOpenItem>  $query
     * @return Builder<AccountingOpenItem>
     */
    public function scopeStillOpen(Builder $query): Builder {
        return $query->whereIn('status', [
            OpenItemStatus::Open->value,
            OpenItemStatus::PartiallySettled->value,
            OpenItemStatus::Disputed->value,
        ]);
    }

    /** Tage seit Fälligkeit; negativ = noch nicht fällig. */
    public function ageInDays(): ?int {
        if ($this->due_date === null) {
            return null;
        }

        return (int) $this->due_date->startOfDay()->diffInDays(now()->startOfDay(), false);
    }

    /** Altersband der Fälligkeitsanalyse (0/30/60/90 — {@see \App\Support\Billing\AgingBuckets::accounting()}). */
    public function agingBucket(): string {
        return \App\Support\Billing\AgingBuckets::accounting()->bucketFor($this->ageInDays());
    }

    public function settledAmount(): Money {
        $original = $this->original_amount ?? Money::zero($this->currency);

        return $original->minus($this->open_amount ?? Money::zero($this->currency));
    }
}
