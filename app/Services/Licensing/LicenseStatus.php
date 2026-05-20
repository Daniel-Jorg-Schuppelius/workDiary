<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

enum LicenseStatus: string {
    case Valid = 'valid';
    case Missing = 'missing';
    case Malformed = 'malformed';
    case BadSignature = 'bad_signature';
    case DomainMismatch = 'domain_mismatch';
    case Expired = 'expired';
    case GracePeriod = 'grace_period';
    case PublicKeyMissing = 'public_key_missing';
    case Tampered = 'tampered';

    public function isUsable(): bool {
        return $this === self::Valid || $this === self::GracePeriod;
    }
}
