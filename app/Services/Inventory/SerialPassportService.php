<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SerialPassportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Inventory;

use App\Support\Auth\OrganizationAccessToken;

/**
 * Zugangstoken des öffentlichen Geräte-Passes (Feature 047/048, E2); die
 * Seite braucht Token UND Freischaltung.
 */
class SerialPassportService extends OrganizationAccessToken {
    public const HASH_KEY = 'serial_passport_token_hash';

    public const HINT_KEY = 'serial_passport_token_hint';

    public const ISSUED_KEY = 'serial_passport_token_issued_at';

    public const ENABLED_KEY = 'serial_passport_enabled';

    /** Der Klartext aus der Zeit vor S-44 verschwindet bei jeder Änderung mit. */
    protected function obsoleteKeys(): array {
        return ['serial_passport_token'];
    }
}
