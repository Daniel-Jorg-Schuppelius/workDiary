<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserKnownDevice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Auth;

use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bekanntes Anmelde-Gerät eines Nutzers (Feature 096, MVP-446).
 *
 * Zwei Arten von Zeilen: der Fingerprint (UA-Familie + Land) für die Meldung
 * „neues Gerät“ und — mit `device_hash` — das über ein signiertes Cookie
 * erkannte Gerät, das für die Anmeldung von außerhalb der Büronetze eine
 * Freigabe braucht (Feature 205, MVP-1655).
 *
 * @property int $id
 * @property int $user_id
 * @property string $fingerprint
 * @property string|null $device_hash
 * @property string $label
 * @property string|null $country
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon|null $approval_requested_at
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property int|null $rejected_by
 */
class UserKnownDevice extends Model {
    protected $fillable = [
        'user_id',
        'fingerprint',
        'device_hash',
        'label',
        'country',
        'latitude',
        'longitude',
        'last_seen_at',
        'approval_requested_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'last_seen_at' => 'datetime',
        'approval_requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejecter(): BelongsTo {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** Freigabeanfrage offen: weder freigegeben noch abgelehnt. */
    public function isPending(): bool {
        return $this->approved_at === null && $this->rejected_at === null;
    }
}
