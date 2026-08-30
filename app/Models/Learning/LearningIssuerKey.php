<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningIssuerKey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Signaturschlüssel einer Organisation für verifizierbare Zertifikate
 * (Feature 149, MVP-751).
 *
 * Der private Schlüssel ist `encrypted` — er darf weder im Klartext in der
 * Datenbank noch in Logs, Audit-Einträgen oder Serialisierungen auftauchen.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $algorithm
 * @property string $public_key
 * @property string $private_key
 * @property string $key_id
 * @property Carbon|null $revoked_at
 */
class LearningIssuerKey extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** Den privaten Schlüssel nie serialisieren oder auditieren. */
    protected $hidden = [
        'private_key',
    ];

    protected $fillable = [
        'organization_id',
        'algorithm',
        'public_key',
        'private_key',
        'key_id',
        'revoked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'private_key' => 'encrypted',
        'revoked_at' => 'datetime',
    ];

    public function isActive(): bool {
        return $this->revoked_at === null;
    }
}
