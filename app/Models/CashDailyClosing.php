<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashDailyClosing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{AppendOnly, Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tagesabschluss einer Kasse (MVP-414): Kassensturz mit Soll/Ist/Differenz.
 * Nach dem Abschluss nimmt der CashBookService keine Buchungen mit
 * booked_on <= closing_date mehr an. Append-only (Vollaudit 2026-07, H14):
 * Die Buchungssperre hängt an dieser Zeile — ein Update/Delete würde
 * abgeschlossene Tage still wieder öffnen; Korrektur nur als neuer Abschluss.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $cash_register_id
 * @property Carbon $closing_date
 * @property string $expected_balance
 * @property string $counted_balance
 * @property string $difference
 * @property string|null $note
 * @property int|null $closed_by
 */
class CashDailyClosing extends Model {
    use AppendOnly;
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'cash_register_id',
        'closing_date',
        'expected_balance',
        'counted_balance',
        'difference',
        'note',
        'closed_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'closing_date' => 'date',
        'expected_balance' => 'decimal:2',
        'counted_balance' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    /** @return BelongsTo<CashRegister, $this> */
    public function register(): BelongsTo {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
