<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EncryptedCasts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Crypto;

use App\Casts\Concerns\EncryptsAtRest;

/**
 * Welche Casts verschlüsseln at rest: Laravels `encrypted…` und die Casts mit
 * Option `encrypted` (`IbanCast::class . ':encrypted'`, MVP-1512). Rekey,
 * Audit-Schwärzung und deren Gates lesen dieselbe Antwort — sonst überlebte
 * eine umgestellte IBAN keine Key-Rotation und stünde im Klartext im Protokoll.
 */
final class EncryptedCasts {
    public static function covers(string $cast): bool {
        if (str_starts_with($cast, 'encrypted')) {
            return true;
        }
        [$class, $options] = array_pad(explode(':', $cast, 2), 2, '');

        return $options !== ''
            && class_exists($class)
            && in_array(EncryptsAtRest::class, class_uses_recursive($class), true)
            && in_array('encrypted', explode(',', $options), true);
    }
}
