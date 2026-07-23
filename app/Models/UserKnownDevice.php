<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserKnownDevice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bekanntes Anmelde-Gerät eines Nutzers (Feature 096, MVP-446).
 *
 * @property int $id
 * @property int $user_id
 * @property string $fingerprint
 * @property string $label
 * @property string|null $country
 * @property \Illuminate\Support\Carbon $last_seen_at
 */
class UserKnownDevice extends Model {
    protected $fillable = [
        'user_id',
        'fingerprint',
        'label',
        'country',
        'last_seen_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
