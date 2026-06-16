<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectUser.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Sources;

/**
 * Normalisierte Repräsentation eines OpenProject-Benutzers. Dient dem User-
 * Mapping (OpenProject-Benutzer → workDiary-Benutzer über die E-Mail-Adresse).
 * Die E-Mail ist nur für Administratoren sichtbar; ist sie null, scheitert das
 * automatische Mapping und der Buchungs-Fallback greift.
 */
final class OpenProjectUser {
    public function __construct(
        public readonly string $externalId,
        public readonly string $name,
        public readonly ?string $email = null,
    ) {}
}
