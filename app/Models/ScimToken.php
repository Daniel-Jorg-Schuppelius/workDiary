<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScimToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Support\{Carbon, Str};

/**
 * Bearer-Token einer Organisation für den SCIM-2.0-Provisioning-Endpunkt
 * (Feature 057, MVP-121). Persistiert wird ausschließlich der SHA-256-Hash;
 * der Klartext wird nur einmal bei der Ausstellung zurückgegeben — Muster wie
 * {@see \App\Models\Location\LocationDeviceToken}. Widerruf über `revoked_at`.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $label
 * @property string $token_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 */
class ScimToken extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** Der Hash ist zwar nicht umkehrbar, wird aber nie serialisiert/auditiert. */
    protected $hidden = [
        'token_hash',
    ];

    protected $fillable = [
        'organization_id',
        'label',
        'token_hash',
        'last_used_at',
        'revoked_at',
        'created_by',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function hashToken(string $plain): string {
        return CryptoHelper::hash($plain);
    }

    /**
     * Stellt ein neues Token für die Organisation aus und gibt [Model, Klartext]
     * zurück. Der Klartext ist danach nicht mehr rekonstruierbar.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(int $organizationId, string $label, ?int $createdBy = null): array {
        $plain = 'scim_' . Str::random(48);

        $token = static::query()->create([
            'organization_id' => $organizationId,
            'label' => $label,
            'token_hash' => static::hashToken($plain),
            'created_by' => $createdBy,
        ]);

        return [$token, $plain];
    }

    public function isActive(): bool {
        return $this->revoked_at === null;
    }

    /** @param Builder<ScimToken> $query */
    public function scopeActive(Builder $query): void {
        $query->whereNull('revoked_at');
    }
}
