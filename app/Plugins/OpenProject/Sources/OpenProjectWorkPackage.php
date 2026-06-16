<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectWorkPackage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Sources;

/**
 * Normalisierte Repräsentation eines OpenProject-Work-Packages (Mapping-Quelle
 * für den Struktur-Sync → workDiary-Aufgabe).
 */
final class OpenProjectWorkPackage {
    public function __construct(
        public readonly string $externalId,
        public readonly string $subject,
        public readonly ?string $projectExternalId = null,
        public readonly ?string $projectName = null,
        public readonly ?string $status = null,
        public readonly ?string $parentExternalId = null,
    ) {}
}
