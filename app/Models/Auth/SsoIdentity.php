<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SsoIdentity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Verknüpfung IdP-Identität ↔ WorkDiary-Konto (Feature 057). Identität ist
 * (Verbindung, Subject) — der Issuer hängt an der Verbindung, zusammen also
 * iss+sub (OIDC) bzw. IdP-EntityId+NameID (SAML). E-Mail ist nie der
 * Schlüssel (mutable/unverified, nOAuth). Audit-Ereignisse laufen über die
 * zugehörige {@see SsoConnection}.
 *
 * @property int $id
 * @property int $sso_connection_id
 * @property int $user_id
 * @property string $subject
 * @property \Illuminate\Support\Carbon|null $last_login_at
 */
class SsoIdentity extends Model {
    protected $fillable = [
        'sso_connection_id',
        'user_id',
        'subject',
        'last_login_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    /** @return BelongsTo<SsoConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(SsoConnection::class, 'sso_connection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
