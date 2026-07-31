<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\{MoneyCast, PercentageCast};
use App\Models\Concerns\{BelongsToOrganization, HasAttachments, HasSqid, HashChainable, HashChained};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kassenbuch-Eintrag (MVP-414): append-only mit revisionssicherer
 * Hash-Kette (config/audit.php, `audit:verify`, GobdLockGuardRuleTest).
 * Korrekturen sind AUSSCHLIESSLICH Storno-Gegenbuchungen
 * ({@see \App\Services\Finance\CashBookService::reverse()}) — nie Update,
 * nie Delete. Einzige Schreibstelle ist der CashBookService.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $cash_register_id
 * @property int $seq_no
 * @property Carbon $booked_on
 * @property string $direction
 * @property \CommonToolkit\ValueObjects\Money|null $amount
 * @property \CommonToolkit\ValueObjects\Percentage|null $tax_rate
 * @property string $purpose
 * @property string|null $counterparty
 * @property int|null $invoice_id
 * @property int|null $reversal_of_id
 * @property int|null $created_by
 * @property string|null $prev_hash
 * @property string|null $hash
 *
 * @phpstan-consistent-constructor
 */
class CashEntry extends Model implements HashChainable {
    use BelongsToOrganization;
    use HasAttachments;
    use HashChained;
    use HasSqid;

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'cash_register_id',
        'seq_no',
        'booked_on',
        'direction',
        'amount',
        'tax_rate',
        'purpose',
        'counterparty',
        'invoice_id',
        'reversal_of_id',
        'created_by',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'seq_no' => 'integer',
        'booked_on' => 'date',
        'amount' => MoneyCast::class . ':currency,2',
        'tax_rate' => PercentageCast::class . ':2',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<CashRegister, $this> */
    public function register(): BelongsTo {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<CashEntry, $this> */
    public function reversalOf(): BelongsTo {
        return $this->belongsTo(CashEntry::class, 'reversal_of_id');
    }

    /** Betrag mit Vorzeichen (Einnahme +, Ausgabe −) für Saldenbildung. */
    public function signedAmount(): float {
        $amount = $this->amount?->toFloat() ?? 0.0;

        return $this->direction === self::DIRECTION_IN ? $amount : -1 * $amount;
    }

    /**
     * In den Hash eingehende Nutzdaten (feste Reihenfolge, treiberunabhängig).
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->nullableInt($this->getAttribute('organization_id')),
            'cash_register_id' => $this->nullableInt($this->getAttribute('cash_register_id')),
            'seq_no' => $this->nullableInt($this->getAttribute('seq_no')),
            'booked_on' => optional($this->booked_on)->toDateString(),
            'direction' => $this->getAttribute('direction'),
            // Roh statt MoneyCast ((string) Money wäre "12.34 EUR"), auf Spalten-
            // skala normalisiert: SQLite liefert decimal als 50 statt "50.00".
            'amount' => $this->hashAmount(),
            'purpose' => $this->getAttribute('purpose'),
            'counterparty' => $this->getAttribute('counterparty'),
            'invoice_id' => $this->nullableInt($this->getAttribute('invoice_id')),
            'reversal_of_id' => $this->nullableInt($this->getAttribute('reversal_of_id')),
            'created_by' => $this->nullableInt($this->getAttribute('created_by')),
            'created_at' => $this->hashCreatedAt(),
        ];
    }

    private function nullableInt(mixed $value): ?int {
        return $value === null ? null : (int) $value;
    }

    private function hashAmount(): string {
        $raw = $this->getAttributes()['amount'] ?? null;

        return $raw === null ? '' : number_format((float) $raw, 2, '.', '');
    }
}
