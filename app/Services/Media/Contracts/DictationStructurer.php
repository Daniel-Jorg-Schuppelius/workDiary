<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DictationStructurer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Media\Contracts;

use App\Models\Platform\Organization;

/**
 * Diktat in Formularfelder gliedern (MVP-1060): definiert vom Diktat,
 * gebunden vom KI-Modul ({@see \App\Services\Ai\Suggestions\DictationStructureService}).
 * Die Null-Bindung antwortet `null` — dann bleibt es beim Transkript.
 */
interface DictationStructurer {
    /**
     * @param  list<string>  $fields  Feldschlüssel des Kontexts (`activity`, `work_done`, `defects`, `note`)
     * @return array<string, string>|null Feld → Text, nur sicher erkannte Felder
     */
    public function structure(Organization $organization, string $text, array $fields, string $locale): ?array;
}
