<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationUploadScanStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Applications;

/**
 * Prüfstand einer öffentlich hochgeladenen Bewerbungsunterlage (MVP-437).
 * Ohne „failed“: ein gescheiterter Scan lässt die Datei in der Quarantäne.
 */
enum JobApplicationUploadScanStatus: string {
    case Pending = 'pending';
    case Clean = 'clean';
    case Rejected = 'rejected';
}
