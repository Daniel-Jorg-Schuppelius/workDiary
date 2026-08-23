<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingTransfer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Interne Umbuchung zwischen Geldkonten (Feature 125, MVP-681).
 *
 * Der Vorgang ist die Klammer um beide Seiten: Bankabhebung und
 * Kasseneinzahlung sind ein Geldfluss, nicht zwei. Die Kopplung verhindert,
 * dass jede Seite einzeln gebucht wird — der Betrag stünde sonst doppelt im
 * Ergebnis.
 */
class AccountingTransfer extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'booked_on',
        'amount',
        'currency',
        'from_account_id',
        'to_account_id',
        'note',
        'from_source_type',
        'from_source_id',
        'to_source_type',
        'to_source_id',
        'accounting_entry_id',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'booked_on' => 'date',
        'amount' => MoneyCast::class,
        'currency' => CurrencyCode::class,
    ];

    /** @return BelongsTo<AccountingAccount, $this> */
    public function fromAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'from_account_id');
    }

    /** @return BelongsTo<AccountingAccount, $this> */
    public function toAccount(): BelongsTo {
        return $this->belongsTo(AccountingAccount::class, 'to_account_id');
    }

    /** @return BelongsTo<AccountingEntry, $this> */
    public function entry(): BelongsTo {
        return $this->belongsTo(AccountingEntry::class, 'accounting_entry_id');
    }

    /** @return MorphTo<Model, $this> */
    public function fromSource(): MorphTo {
        return $this->morphTo('from_source');
    }

    /** @return MorphTo<Model, $this> */
    public function toSource(): MorphTo {
        return $this->morphTo('to_source');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Idempotenzschlüssel der erzeugten Buchung. */
    public function sourceKey(): string {
        return 'transfer:' . $this->getKey();
    }
}
