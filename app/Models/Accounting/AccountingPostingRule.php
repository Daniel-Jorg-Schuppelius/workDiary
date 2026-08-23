<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingPostingRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buchungsregel (Feature 125, MVP-673): Quelle + Rolle → Konto.
 *
 * `match_criteria` ist eine Merkmalsmenge (z. B. `{"tax_rate":"19.00"}`).
 * Eine Regel passt, wenn **alle** ihre Merkmale im Kontext übereinstimmen;
 * die leere Menge ist die Auffangregel. Bei mehreren Treffern gewinnt die
 * höhere Priorität, danach die spezifischere Regel — nie der Zufall der
 * Einfügereihenfolge.
 *
 * @property PostingSourceKind $source_kind
 * @property PostingAccountRole $role
 * @property array<string, mixed>|null $match_criteria
 */
class AccountingPostingRule extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'source_kind',
        'role',
        'accounting_account_id',
        'accounting_tax_code_id',
        'match_criteria',
        'priority',
        'version',
        'valid_from',
        'valid_to',
        'is_active',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'source_kind' => PostingSourceKind::class,
        'role' => PostingAccountRole::class,
        'match_criteria' => 'array',
        'priority' => 'integer',
        'version' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<AccountingAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    /** @return BelongsTo<AccountingTaxCode, $this> */
    public function taxCode(): BelongsTo {
        return $this->belongsTo(AccountingTaxCode::class, 'accounting_tax_code_id');
    }

    /**
     * @param  Builder<AccountingPostingRule>  $query
     * @return Builder<AccountingPostingRule>
     */
    public function scopeValidOn(Builder $query, CarbonInterface $date): Builder {
        return $query->where('is_active', true)
            ->whereDate('valid_from', '<=', $date->toDateString())
            ->where(function (Builder $inner) use ($date): void {
                $inner->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date->toDateString());
            });
    }

    /**
     * Passt die Regel zu diesem Merkmalskontext?
     *
     * @param  array<string, mixed>  $context
     */
    public function matches(array $context): bool {
        foreach (($this->match_criteria ?? []) as $key => $expected) {
            if (! array_key_exists($key, $context)) {
                return false;
            }
            if ((string) $context[$key] !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    /** Spezifität = Anzahl geforderter Merkmale (Tiebreaker nach Priorität). */
    public function specificity(): int {
        return count($this->match_criteria ?? []);
    }

    /** Kennung der Regelfassung für den Buchungs-Snapshot. */
    public function versionTag(): string {
        return 'rule:' . $this->id . '@v' . $this->version;
    }
}
