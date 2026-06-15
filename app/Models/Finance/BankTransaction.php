<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankTransaction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\{MatchStatus, TransactionDirection};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Finance\BankTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Normalisierter Bankumsatz (Feature 045, „Priorität 3").
 *
 * Personenbezogene Felder (counterparty_name/-iban, purpose) sind verschlüsselt;
 * das Matching nutzt ausschließlich die Ableitungen counterparty_iban_hash,
 * extracted_refs, amount, direction und die Datumsfelder. Bankumsätze sind NIE
 * editierbar — nur `match_status` wird durch Zuordnungsaktionen verändert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bank_statement_id
 * @property int $line_index
 * @property Carbon $booking_date
 * @property Carbon|null $valuta_date
 * @property string $amount
 * @property TransactionDirection $direction
 * @property string $currency
 * @property string|null $end_to_end_id
 * @property string|null $mandate_ref
 * @property string|null $counterparty_name
 * @property string|null $counterparty_iban
 * @property string|null $counterparty_iban_hash
 * @property string|null $purpose
 * @property array<int, string>|null $extracted_refs
 * @property bool $is_reversal
 * @property string $fingerprint
 * @property MatchStatus $match_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BankTransaction extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<BankTransactionFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'bank_statement_id',
        'line_index',
        'booking_date',
        'valuta_date',
        'amount',
        'direction',
        'currency',
        'end_to_end_id',
        'mandate_ref',
        'counterparty_name',
        'counterparty_iban',
        'counterparty_iban_hash',
        'purpose',
        'extracted_refs',
        'is_reversal',
        'fingerprint',
        'match_status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'line_index' => 'integer',
        'booking_date' => 'date',
        'valuta_date' => 'date',
        'amount' => 'decimal:2',
        'direction' => TransactionDirection::class,
        'counterparty_name' => 'encrypted',
        'counterparty_iban' => 'encrypted',
        'purpose' => 'encrypted',
        'extracted_refs' => 'array',
        'is_reversal' => 'boolean',
        'match_status' => MatchStatus::class,
    ];

    /** @return BelongsTo<BankStatement, $this> */
    public function statement(): BelongsTo {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isCredit(): bool {
        return $this->direction === TransactionDirection::Credit;
    }

    /** Signierter Betrag aus Kontosicht (Haben +, Soll −). */
    public function signedAmount(): float {
        return $this->direction->sign() * (float) $this->amount;
    }
}
