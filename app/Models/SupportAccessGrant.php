<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportAccessGrant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Temporäre Supportfreigabe (Rang 64): vom Kundenadmin erteilt, zeitlich
 * begrenzt, jederzeit widerrufbar. Impersonation (user.impersonate) ist nur
 * bei aktiver Freigabe zulässig; `read_only` beschränkt die Support-Session
 * auf lesende Requests.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $granted_by_user_id
 * @property int|null $granted_to_user_id
 * @property string $scope
 * @property string $purpose
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property string|null $revoked_reason
 */
class SupportAccessGrant extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const SCOPE_READ_ONLY = 'read_only';

    public const SCOPE_FULL = 'full';

    /** Obergrenze der Freigabedauer (Soll-Konzept §5.1). */
    public const MAX_DURATION_DAYS = 7;

    protected $fillable = [
        'organization_id',
        'granted_by_user_id',
        'granted_to_user_id',
        'scope',
        'purpose',
        'expires_at',
        'revoked_at',
        'revoked_reason',
    ];

    protected function casts(): array {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantedTo(): BelongsTo {
        return $this->belongsTo(User::class, 'granted_to_user_id');
    }

    public function isActive(): bool {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * Aktive Freigabe für einen Support-User: entweder offen (granted_to
     * null) oder gezielt auf diesen Account ausgestellt.
     */
    public static function activeFor(int $organizationId, int $supportUserId): ?self {
        return self::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->where(function (Builder $q) use ($supportUserId): void {
                $q->whereNull('granted_to_user_id')->orWhere('granted_to_user_id', $supportUserId);
            })
            ->orderByDesc('expires_at')
            ->first();
    }
}
