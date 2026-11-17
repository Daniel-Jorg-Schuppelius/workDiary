<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AutosavesFieldDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Services\Fields\{FieldSchema, FieldValues};

/**
 * Träger eines Feldschema-Dokuments, das je Feld gespeichert wird (Feature 203,
 * MVP-1650): Formulareinsendung im Entwurf, später die Aufnahmeakte
 * (Feature 200). Alle Träger nutzen denselben Endpunkt
 * (`field-documents.autosave`, {@see \App\Services\Fields\FieldAutosaver}).
 *
 * Bedingungen an den Träger:
 *  - Eloquent-Modell mit Morph-Alias (der Endpunkt nennt den Alias),
 *  - seine Policy kennt die Fähigkeit `autosave` (wer Zwischenstände schreibt),
 *  - die Tabelle steht im Manifest seines Moduls (der Endpunkt prüft, ob das
 *    Modul für die Organisation aktiv ist).
 *
 * Geprüft wird nur das gespeicherte Feld; Pflichtfelder prüft der Träger
 * erst beim Abschluss. Die Version schützt vor stillem Überschreiben:
 * wer mit einer veralteten Version speichert, bekommt einen Konflikt.
 */
interface AutosavesFieldDocument {
    /** Schema, gegen das ein einzelnes Feld geprüft wird (eingefrorener Stand des Trägers). */
    public function fieldDocumentSchema(): FieldSchema;

    /** Gespeicherte Werte samt Ergänzungen. */
    public function fieldDocumentValues(): FieldValues;

    /** Aktuelle Version für die Konfliktprüfung. */
    public function fieldDocumentVersion(): int;

    /** Nimmt der Träger noch Zwischenstände an (etwa nur im Entwurf)? */
    public function acceptsFieldAutosave(): bool;

    /**
     * Werte und neue Version schreiben. Läuft in der Transaktion des
     * {@see \App\Services\Fields\FieldAutosaver}; Zwischenstände schreiben
     * kein Änderungsprotokoll je Feld — protokolliert wird der Abschluss.
     */
    public function storeFieldAutosave(FieldValues $values, int $version): void;
}
