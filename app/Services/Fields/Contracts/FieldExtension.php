<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldExtension.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields\Contracts;

use App\Services\Fields\FieldDefinition;

/**
 * Fachtyp im Feldschema-Baustein (MVP-866): ein Modul erweitert einen
 * Grundtyp um eigene Validierung, Wertform und Anzeige und nennt die Klasse
 * in `extensions()` seines Manifests. Die Definition trägt den Schlüssel in
 * `extension`.
 */
interface FieldExtension {
    /** Schlüssel, unter dem Definitionen den Fachtyp referenzieren (`defect`, `measurement.timestamped`). */
    public function key(): string;

    /**
     * Laravel-Regeln für den Wert (ohne `required`/`nullable`, das setzt der Validator).
     *
     * @return list<mixed>
     */
    public function rules(FieldDefinition $field): array;

    /** Validierte Eingabe → typtreuer Speicherwert. */
    public function normalize(FieldDefinition $field, mixed $raw): mixed;

    /** Anzeige-/Druckwert. */
    public function display(FieldDefinition $field, mixed $value): string;

    /** Eigene Eingabe-View (`x-field-input` bindet sie ein), null = Grundtyp. */
    public function inputView(): ?string;
}
