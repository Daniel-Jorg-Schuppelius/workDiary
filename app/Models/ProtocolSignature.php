<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolSignature.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\IpAddressCast;
use App\Enums\Protocol\{ProtocolSignatureMethod, ProtocolSignatureRole};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $protocol_id
 * @property ProtocolSignatureRole $role
 * @property string $signer_name
 * @property string|null $signer_email
 * @property \Illuminate\Support\Carbon $signed_at
 * @property ProtocolSignatureMethod $method
 * @property string|null $signature_image_path
 * @property \CommonToolkit\ValueObjects\IpAddress|null $ip
 * @property string|null $user_agent
 * @property string $hash
 */
class ProtocolSignature extends Model {
    protected $fillable = [
        'protocol_id',
        'role',
        'signer_name',
        'signer_email',
        'signed_at',
        'method',
        'signature_image_path',
        'ip',
        'user_agent',
        'hash',
    ];

    protected $casts = [
        'role' => ProtocolSignatureRole::class,
        'method' => ProtocolSignatureMethod::class,
        'signed_at' => 'datetime',
        'ip' => IpAddressCast::class,
    ];

    /** @return BelongsTo<Protocol, $this> */
    public function protocol(): BelongsTo {
        return $this->belongsTo(Protocol::class);
    }
}
