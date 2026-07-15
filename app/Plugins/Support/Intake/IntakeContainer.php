<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeContainer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Intake;

/**
 * Wählbarer Quell-Container (Feature 080, MVP-351): Dropbox-Namespace,
 * OneDrive-Drive/SharePoint-Bibliothek oder Google-Ablage/Shared Drive.
 * `kind` ist providerspezifische Anzeigeinformation (z. B. "drive",
 * "sharedDrive", "documentLibrary"), keine Steuerlogik.
 */
final readonly class IntakeContainer {
    public function __construct(
        public string $id,
        public string $label,
        public ?string $kind = null,
    ) {}
}
