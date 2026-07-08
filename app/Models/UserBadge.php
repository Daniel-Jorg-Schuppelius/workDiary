<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserBadge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RFID-/NFC-Badge eines Nutzers (Feature 061, MVP-130). Die Kennung wird nur als
 * SHA-256-Hash gespeichert (keine Klartext-Kennung in DB/Logs). Ein verlorener
 * Badge wird über `revoked_at` gesperrt und einem neuen Datensatz neu zugeordnet.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string|null $label
 * @property string $badge_hash
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class UserBadge extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $table = 'user_badges';

    /** Die (gehashte) Kennung nie serialisieren/auditieren. */
    protected $hidden = [
        'badge_hash',
    ];

    protected $fillable = [
        'organization_id',
        'user_id',
        'label',
        'badge_hash',
        'revoked_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    public static function hashBadge(string $uid): string {
        return CryptoHelper::hash(trim($uid));
    }

    public function isActive(): bool {
        return $this->revoked_at === null;
    }

    /** @param Builder<UserBadge> $query */
    public function scopeActive(Builder $query): void {
        $query->whereNull('revoked_at');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
