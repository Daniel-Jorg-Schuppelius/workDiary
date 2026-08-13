<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountEntry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Journal-Buchung eines Zeitkontos (MVP-526) — append-only: Korrektur
 * geschieht per Storno-Gegenbuchung (`reversal_of_id`), nie per Update.
 *
 * BEWUSST OHNE automatisches Änderungs-Logging (Muster ComplianceFinding):
 * das Journal ist selbst der Nachweis; der Posting-Lauf würde sonst je
 * Zeile ein Audit-Echo erzeugen. Manuelle Buchungen und Stornos werden
 * explizit über {@see Auditable::audit()} in die Hash-Kette geschrieben.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $time_account_id
 * @property int $user_id
 * @property Carbon $booking_date
 * @property string $quantity
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $note
 * @property int|null $posted_by
 * @property int|null $reversal_of_id
 */
class TimeAccountEntry extends Model {
    use Auditable;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'time_account_id',
        'user_id',
        'booking_date',
        'quantity',
        'source_type',
        'source_id',
        'note',
        'posted_by',
        'reversal_of_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'booking_date' => 'date',
        'quantity' => 'decimal:2',
    ];

    /** Kein Auto-Logging (bewusst) — s. Klassen-Doc. */
    public static function bootAuditable(): void {
        // no-op
    }

    /** @return BelongsTo<TimeAccount, $this> */
    public function account(): BelongsTo {
        return $this->belongsTo(TimeAccount::class, 'time_account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TimeAccountEntry, $this> */
    public function reversalOf(): BelongsTo {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
