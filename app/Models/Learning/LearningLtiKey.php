<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiKey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Jose\Component\Core\JWK;

/**
 * Signaturschlüssel der Instanz für LTI 1.3 (Feature 149). Bewusst global:
 * Aussteller ist die Anwendung, nicht die Organisation.
 *
 * @property int $id
 * @property string $kid
 * @property array<string, mixed> $public_jwk
 * @property array<string, mixed> $private_jwk
 * @property Carbon|null $activated_at
 * @property Carbon|null $retired_at
 */
class LearningLtiKey extends Model {
    /** Den privaten Schlüssel nie serialisieren. */
    protected $hidden = [
        'private_jwk',
    ];

    protected $fillable = [
        'kid',
        'public_jwk',
        'private_jwk',
        'activated_at',
        'retired_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'public_jwk' => 'array',
        'private_jwk' => 'encrypted:array',
        'activated_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    public function privateKey(): JWK {
        return new JWK($this->private_jwk);
    }

    public function publicKey(): JWK {
        return new JWK($this->public_jwk);
    }
}
