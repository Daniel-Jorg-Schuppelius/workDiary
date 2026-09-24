<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceStatusProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset\Contracts;

use App\Enums\AssetCompliance\AssetComplianceStatus;
use App\Models\Asset\Asset;

/**
 * Prüfstatus einer Anlage (MVP-863): definiert vom Anlagenkern, gebunden vom
 * Modul Anlagen-Compliance. Null-Bindung: „nicht anwendbar" — ohne das Modul
 * gibt es keine Prüfprofile, also auch keine Sperre aus überfälligen Prüfungen.
 */
interface AssetComplianceStatusProvider {
    public function statusFor(Asset $asset): AssetComplianceStatus;

    /** Überfällige Prüfungen als Sperren nachziehen; Anzahl der gesetzten Sperren. */
    public function syncOverdueBlocks(Asset $asset): int;
}
