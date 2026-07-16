<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocationDeviceToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registriertes Standort-Erfassungsgerät (OwnTracks/Traccar/Browser) je Nutzer.
 * Persistiert wird nur der Token-Hash; `revoked_at` trennt das Gerät.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $label
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
