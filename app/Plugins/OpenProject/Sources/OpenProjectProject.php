<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectProject.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Sources;

/**
 * Normalisierte Repräsentation eines OpenProject-Projekts (Mapping-Quelle für
 * den Struktur-Sync → workDiary-Projekt).
 */
final class OpenProjectProject {
    public function __construct(
        public readonly string $externalId,
        public readonly string $name,
        public readonly ?string $identifier = null,
        public readonly bool $active = true,
        public readonly ?string $parentExternalId = null,
    ) {}
}
