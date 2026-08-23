<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\AccountingEntryStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use RuntimeException;

/**
 * Buchung im Journal (Feature 125, MVP-672).
 *
 * Bis `posted` ist die Buchung ein Arbeitsstand. Danach greift der
 * Freeze-Guard (Muster {@see \App\Models\Finance\DatevBookingBatch}): Es
 * bleibt genau eine erlaubte Änderung — der Storno-Vermerk, den
 * {@see \App\Services\Accounting\JournalService::reverse()} setzt und der in
 * der Hash-Kette `accounting_events` nachgewiesen wird. Alles andere wirft.
 *
 * @property AccountingEntryStatus $status
 * @property CurrencyCode $currency
 * @property array<string, mixed>|null $snapshot
 */
class AccountingEntry extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** Felder, die nach der Festschreibung noch geschrieben werden dürfen. */
    private const POST_FREEZE_WRITABLE = ['status', 'reversed_by_entry_id', 'reversal_reason', 'updated_at'];

    protected $fillable = [
        'organization_id',
        'accounting_fiscal_year_id',
        'accounting_period_id',
        'journal_no',
        'booked_on',
        'document_on',
        'status',
        'memo',
        'document_reference',
        'currency',
        'source_type',
        'source_id',
        'source_key',
        'rule_version',
        'snapshot',
        'reverses_entry_id',
        'reversed_by_entry_id',
        'reversal_reason',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AccountingEntryStatus::class,
        'currency' => CurrencyCode::class,
        'booked_on' => 'date',
        'document_on' => 'date',
        'posted_at' => 'datetime',
        'snapshot' => 'array',
        'journal_no' => 'integer',
    ];

    protected static function booted(): void {
        static::updating(function (self $entry): void {
            $original = $entry->getOriginal('status');
            $status = $original instanceof AccountingEntryStatus
                ? $original
                : AccountingEntryStatus::tryFrom((string) $original);

            if ($status === null || $status->isMutable()) {
                return;
            }

            $changed = array_keys($entry->getDirty());
            if (array_diff($changed, self::POST_FREEZE_WRITABLE) !== []) {
                throw new RuntimeException('Festgeschriebene Buchung: Änderung nur über eine Gegenbuchung.');
            }

            // Der Statusweg ist einbahnig: posted → reversed, nie zurück.
            if (in_array('status', $changed, true)
                && ! ($status === AccountingEntryStatus::Posted && $entry->status === AccountingEntryStatus::Reversed)) {
                throw new RuntimeException('Festgeschriebene Buchung: unzulässiger Statuswechsel.');
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->status->isPosted()) {
                throw new RuntimeException('Festgeschriebene Buchung darf nicht gelöscht werden.');
            }
        });
    }

    /** @return HasMany<AccountingEntryLine, $this> */
    public function lines(): HasMany {
        return $this->hasMany(AccountingEntryLine::class)->orderBy('line_no');
    }

    /** @return BelongsTo<AccountingPeriod, $this> */
    public function period(): BelongsTo {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    /** @return BelongsTo<AccountingFiscalYear, $this> */
    public function fiscalYear(): BelongsTo {
        return $this->belongsTo(AccountingFiscalYear::class, 'accounting_fiscal_year_id');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<AccountingEntry, $this> */
    public function reverses(): BelongsTo {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    /** @return BelongsTo<AccountingEntry, $this> */
    public function reversedBy(): BelongsTo {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** Summe der Sollseite. */
    public function debitTotal(): Money {
        return Money::sum(
            $this->lines->map(fn (AccountingEntryLine $line): Money => $line->debit ?? Money::zero($this->currency)),
            $this->currency,
        );
    }

    /** Summe der Habenseite. */
    public function creditTotal(): Money {
        return Money::sum(
            $this->lines->map(fn (AccountingEntryLine $line): Money => $line->credit ?? Money::zero($this->currency)),
            $this->currency,
        );
    }

    /** Die Grundinvariante: beide Seiten gleich und nicht null. */
    public function isBalanced(): bool {
        $debit = $this->debitTotal();

        return $debit->equals($this->creditTotal()) && ! $debit->isZero();
    }
}
