<?php
/*
 * Created on   : Tue Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TwoFactorCredential.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Auth;

use App\Enums\Auth\TwoFactorType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein eingerichteter zweiter Faktor eines Nutzers (TOTP/E-Mail/WebAuthn).
 * `secret`/`data` sind at-rest verschluesselt.
 *
 * @property TwoFactorType $type
 * @property string|null $secret
 * @property array<string,mixed>|null $data
 */
class TwoFactorCredential extends Model {
    protected $table = 'two_factor_credentials';

    protected $fillable = [
        'user_id', 'type', 'label', 'secret', 'data', 'credential_id', 'confirmed_at', 'last_used_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'type' => TwoFactorType::class,
        'secret' => 'encrypted',
        'data' => 'encrypted:array',
        'confirmed_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function isConfirmed(): bool {
        return $this->confirmed_at !== null;
    }
}
