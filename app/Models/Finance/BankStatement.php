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

use App\Enums\Finance\{BalanceCheck, BankStatementFormat};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Finance\BankStatementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Importierter Bankauszug (Feature 045, „Priorität 3").
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $bank_account_id
 * @property BankStatementFormat $source_format
 * @property string $file_path
 * @property string $file_hash
 * @property string|null $statement_iban_hash
 * @property string|null $opening_balance
 * @property string|null $closing_balance
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property int $tx_count
 * @property BalanceCheck $balance_check
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
        'file_path',
        'file_hash',
        'statement_iban_hash',
        'opening_balance',
        'closing_balance',
        'period_from',
        'period_to',
        'tx_count',
        'balance_check',
        'imported_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'source_format' => BankStatementFormat::class,
        'balance_check' => BalanceCheck::class,
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
}
