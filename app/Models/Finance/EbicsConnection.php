<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\EbicsConnectionStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasJournal, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EBICS-Bankzugang eines Bankkontos (MVP-124, EBICS 3.0). Der Schlüsselbund
 * ist mit `keyring_secret` geschützt und beides at-rest verschlüsselt; nie
 * serialisiert, nie auditiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $bank_account_id
 * @property string $host_url
 * @property string $ebics_host
 * @property string $ebics_partner
 * @property string $ebics_user
 * @property EbicsConnectionStatus $status
 * @property string|null $keyring
 * @property string|null $keyring_secret
 * @property Carbon|null $keys_created_at
 * @property Carbon|null $initialized_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $statements_until
 * @property Carbon|null $last_fetched_at
 * @property string|null $last_error
 * @property int|null $created_by
 */
class EbicsConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasJournal;
    use HasSqid;

    protected static string $journalClass = EbicsConnectionEvent::class;

    protected $fillable = [
        'organization_id', 'bank_account_id', 'host_url', 'ebics_host', 'ebics_partner', 'ebics_user', 'status',
        'keyring', 'keyring_secret', 'keys_created_at', 'initialized_at', 'activated_at', 'statements_until',
        'last_fetched_at', 'last_error', 'created_by',
    ];

    /** Verborgen heißt auch: nie im Audit-Diff. */
    protected $hidden = ['keyring', 'keyring_secret'];

    /** @var array<string, string> */
    protected $casts = [
        'status' => EbicsConnectionStatus::class,
        'keyring' => 'encrypted',
        'keyring_secret' => 'encrypted',
        'keys_created_at' => 'datetime',
        'initialized_at' => 'datetime',
        'activated_at' => 'datetime',
        'statements_until' => 'date',
        'last_fetched_at' => 'datetime',
    ];

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo {
        return $this->belongsTo(BankAccount::class);
    }

    public function isActive(): bool {
        return $this->status === EbicsConnectionStatus::Active;
    }
}
