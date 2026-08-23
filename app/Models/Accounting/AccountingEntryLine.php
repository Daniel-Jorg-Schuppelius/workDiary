<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingEntryLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Models\{Asset, Project};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use RuntimeException;

/**
 * Buchungszeile (Feature 125, MVP-672).
 *
 * Soll und Haben stehen in getrennten Spalten; genau eine Seite je Zeile ist
 * besetzt. Der Freeze folgt der Buchung: Zeilen einer festgeschriebenen
 * Buchung sind unveränderlich — sonst ließe sich die Summe nachträglich
 * verschieben, ohne dass der Kopf es merkt.
 *
 * Bewusst NICHT `Auditable`: Der Nachweis hängt an der Buchung und an der
 * Hash-Kette; ein zweiter Audit-Eintrag je Zeile brächte nur Rauschen.
 *
 * @property CurrencyCode $currency
 */
class AccountingEntryLine extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'accounting_entry_id',
        'line_no',
        'accounting_account_id',
        'debit',
        'credit',
        'currency',
        'accounting_tax_code_id',
        'tax_amount',
        'counterparty_type',
        'counterparty_id',
        'project_id',
        'asset_id',
        'cost_group',
        'memo',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => CurrencyCode::class,
        'debit' => MoneyCast::class . ':currency,2',
        'credit' => MoneyCast::class . ':currency,2',
        'tax_amount' => MoneyCast::class . ':currency,2',
        'line_no' => 'integer',
    ];

    protected static function booted(): void {
        static::updating(function (self $line): void {
            $line->assertEntryMutable();
        });

        static::deleting(function (self $line): void {
            $line->assertEntryMutable();
        });
    }

    /** @return BelongsTo<AccountingEntry, $this> */
    public function entry(): BelongsTo {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    /** @return BelongsTo<AccountingAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    /** @return BelongsTo<AccountingTaxCode, $this> */
    public function taxCode(): BelongsTo {
        return $this->belongsTo(AccountingTaxCode::class, 'accounting_tax_code_id');
    }

    /** @return MorphTo<Model, $this> */
    public function counterparty(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** Betrag mit Vorzeichen aus Sicht des Kontos (Soll +, Haben −). */
    public function signedAmount(): Money {
        $debit = $this->debit ?? Money::zero($this->currency);
        $credit = $this->credit ?? Money::zero($this->currency);

        return $debit->minus($credit);
    }

    private function assertEntryMutable(): void {
        $entry = $this->entry()->first();
        if ($entry instanceof AccountingEntry && $entry->status->isPosted()) {
            throw new RuntimeException('Zeile einer festgeschriebenen Buchung darf nicht geändert werden.');
        }
    }
}
