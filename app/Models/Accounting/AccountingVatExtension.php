<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVatExtension.php
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dauerfristverlängerung eines Kalenderjahres (Feature 125, MVP-684).
 *
 * Die Verlängerung selbst gilt dauerhaft weiter (§ 46 S. 2 UStDV); die
 * Sondervorauszahlung ist jedes Jahr neu anzumelden und zu zahlen. Deshalb
 * eine Zeile je Jahr statt eines Schalters am Profil.
 */
class AccountingVatExtension extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'year',
        'granted_on',
        'special_prepayment_amount',
        'currency',
        'special_prepayment_entry_id',
        'note',
        'actor_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'year' => 'integer',
        'granted_on' => 'date',
        'special_prepayment_amount' => MoneyCast::class,
        'currency' => CurrencyCode::class,
    ];

    /** @return BelongsTo<AccountingEntry, $this> */
    public function specialPrepaymentEntry(): BelongsTo {
        return $this->belongsTo(AccountingEntry::class, 'special_prepayment_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** Idempotenzschlüssel der Buchung der Sondervorauszahlung. */
    public function prepaymentSourceKey(): string {
        return 'vat-special-prepayment:' . $this->year;
    }
}
