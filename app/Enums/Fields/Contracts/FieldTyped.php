<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldTyped.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Fields\Contracts;

use App\Enums\Fields\FieldType;

/**
 * Fach-Enum, das seine Fälle auf den Typkatalog des Feldschema-Bausteins
 * abbildet (MVP-867): Protokollpunkte, Prozedurschritte und Umfragefragen
 * behalten ihre Werte (Hash, Konfiguration), Eingabe/Validierung/Anzeige
 * kommen aus {@see FieldType} bzw. einer registrierten `FieldExtension`.
 */
interface FieldTyped {
    /** Grundtyp im Baustein; null für Fälle ohne Erfassungswert (Warteschritt, Bestätigung). */
    public function fieldType(): ?FieldType;

    /** Schlüssel des Fachtyps (`FieldDefinition::extension`), wenn Validierung/Anzeige über den Grundtyp hinausgehen. */
    public function fieldExtension(): ?string;
}
