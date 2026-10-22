<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BankStatement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\{BalanceCheck, BankStatementFormat, StatementChainCheck};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use App\Support\Query\DateRange;
use Database\Factories\Finance\BankStatementFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Importierter Bankauszug (Feature 045, „Priorität 3").
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $bank_account_id
 * @property BankStatementFormat $source_format
 * @property bool $is_provisional CAMT.052/054 und PAIN: Vorschau ohne Abgleich (MVP-1274)
 * @property Carbon|null $superseded_at Zeitpunkt, zu dem der verbindliche Auszug ihn ersetzt hat
 * @property string $file_path
 * @property string $file_hash
 * @property string|null $statement_iban_hash
 * @property string|null $opening_balance
 * @property string|null $closing_balance
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property int $tx_count
 * @property BalanceCheck $balance_check
 * @property StatementChainCheck|null $chain_check Anschluss an den Vorauszug des Kontos (MVP-1279)
 * @property int|null $imported_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BankStatement extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<BankStatementFactory> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'bank_account_id',
        'source_format',
        'is_provisional',
        'superseded_at',
        'file_path',
        'file_hash',
        'statement_iban_hash',
        'opening_balance',
        'closing_balance',
        'period_from',
        'period_to',
        'tx_count',
        'balance_check',
        'chain_check',
        'imported_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'source_format' => BankStatementFormat::class,
        'is_provisional' => 'boolean',
        'superseded_at' => 'datetime',
        'balance_check' => BalanceCheck::class,
        'chain_check' => StatementChainCheck::class,
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'period_from' => 'date',
        'period_to' => 'date',
        'tx_count' => 'integer',
    ];

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function importedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    /** @return HasMany<BankTransaction, $this> */
    public function transactions(): HasMany {
        return $this->hasMany(BankTransaction::class)->orderBy('line_index');
    }

    /**
     * Andere Auszüge desselben Kontos: gewähltes Bankkonto oder dieselbe IBAN.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function sameAccountAs(Builder $query, self $statement): void {
        $query->where('organization_id', $statement->organization_id)
            ->whereKeyNot($statement->id)
            ->where(function (Builder $account) use ($statement): void {
                $account->whereIn('id', []);
                if ($statement->bank_account_id !== null) {
                    $account->orWhere('bank_account_id', $statement->bank_account_id);
                }
                if ($statement->statement_iban_hash !== null) {
                    $account->orWhere('statement_iban_hash', $statement->statement_iban_hash);
                }
            });
    }

    /** Letzter verbindlicher Auszug des Kontos vor diesem — Gegenstück der Saldenkette (MVP-1279). */
    public function previousStatement(): ?self {
        if ($this->period_from === null) {
            return null;
        }

        return self::query()
            ->sameAccountAs($this)
            ->where('is_provisional', false)
            ->whereNotNull('closing_balance')
            ->where('period_to', '<', DateRange::dayAfter($this->period_from))
            ->orderByDesc('period_to')
            ->orderByDesc('id')
            ->first();
    }
}
