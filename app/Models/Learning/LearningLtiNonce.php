<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiNonce.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Learning;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Verbrauchte LTI-Nonce (Feature 149) — nur als Abdruck, global wie der Aussteller.
 *
 * @property int $id
 * @property string $nonce_hash
 * @property Carbon $expires_at
 */
class LearningLtiNonce extends Model {
    protected $fillable = [
        'nonce_hash',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
