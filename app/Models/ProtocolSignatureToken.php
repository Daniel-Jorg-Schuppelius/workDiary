<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSignatureToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Protocol\ProtocolSignatureRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einmal verwendbarer Signaturlink fuer `emailLink`-Methode (MVP-022 §3.3).
 *
 * Der Klartext-Token wird *nicht* gespeichert; statt dessen ein
 * SHA-256-Hash. Vergleichbar mit Laravels Password-Reset-Mechanismus.
 *
 * @property int $id
 * @property int $protocol_id
 * @property ProtocolSignatureRole $role
 * @property string|null $signer_name
 * @property string|null $signer_email
 * @property string $token_hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $opened_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property int|null $signed_signature_id
 * @property int $created_by_user_id
 */
class ProtocolSignatureToken extends Model {
    protected $fillable = [
        'protocol_id',
        'role',
        'signer_name',
        'signer_email',
        'token_hash',
        'expires_at',
        'opened_at',
        'used_at',
        'signed_signature_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'role' => ProtocolSignatureRole::class,
        'expires_at' => 'datetime',
        'opened_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /** @return BelongsTo<Protocol, $this> */
    public function protocol(): BelongsTo {
        return $this->belongsTo(Protocol::class);
    }

    /** @return BelongsTo<ProtocolSignature, $this> */
    public function signature(): BelongsTo {
        return $this->belongsTo(ProtocolSignature::class, 'signed_signature_id');
    }

    public function isUsable(): bool {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
