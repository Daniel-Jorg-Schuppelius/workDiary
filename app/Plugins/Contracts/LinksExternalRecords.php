<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinksExternalRecords.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Integration\ExternalReference;

/**
 * Plugin kennt die Adresse eines übernommenen Datensatzes im Fremdsystem
 * (Herkunftskennzeichen, Feature 181). Der Aufrufer prüft das Schema selbst.
 */
interface LinksExternalRecords {
    public function externalRecordUrl(ExternalReference $reference): ?string;
}
