<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationDeviceToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Location;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pro-Gerät-Token für den Standort-Ingest. Der Klartext wird nur einmal bei der
 * Ausstellung zurückgegeben; persistiert wird ausschließlich der SHA-256-Hash.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $label
 * @property string $token_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 */
class LocationDeviceToken extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'user_id',
        'label',
        'token_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function hashToken(string $plain): string {
        return hash('sha256', $plain);
    }

    /**
     * Erzeugt ein neues Token für den Nutzer und gibt [Model, Klartext] zurück.
     * Der Klartext ist danach nicht mehr rekonstruierbar.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(User $user, string $label): array {
        $plain = Str::random(48);

        $token = static::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'label' => $label,
            'token_hash' => static::hashToken($plain),
        ]);

        return [$token, $plain];
    }

    public function isActive(): bool {
        return $this->revoked_at === null;
    }

    /** @param Builder<LocationDeviceToken> $query */
    public function scopeActive(Builder $query): void {
        $query->whereNull('revoked_at');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
