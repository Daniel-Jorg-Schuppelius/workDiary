<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Match;

use App\Models\Organization;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * Beschreibt, wie eine Ziel-Entität (Customer, Supplier, Project, …) gegen
 * Remote-Daten abgeglichen wird: die geordnete Strategie-Kette, das Mapping
 * Remote→lokales Schema, die Kandidaten-Query und das Anlegen neuer Datensätze.
 *
 * Eine Implementierung pro Entität ersetzt die heute pro Plugin duplizierte
 * Match-Logik ({@see \App\Plugins\Lexoffice\LexofficeContactSync::findLocalMatch}
 * etc.). Siehe ../WorkDiary-Architecture/features/053-datenimport-integrations-drehscheibe.md.
 *
 * Wichtig: Die QUELLSPEZIFISCHE Übersetzung (Lexoffice-JSON, Toggl-flat, CSV-Zeile
 * …) ins lokale Feldschema bleibt beim jeweiligen Importer. Das Profil arbeitet
 * ausschließlich auf bereits gemappten Wertesätzen (Schlüssel = lokale Spalten).
 */
interface MatchProfile {
    /**
     * Vollqualifizierter Modell-Klassenname der Ziel-Entität.
     *
     * @return class-string<Model>
     */
    public function targetType(): string;

    /** @return list<MatchStrategy> geordnet (first-confident gewinnt) */
    public function strategies(): array;

    /**
     * Org-gescopte Basis-Query für Kandidaten (inkl. Ausschluss archivierter).
     *
     * @return Builder<Model>
     */
    public function candidates(Organization $organization): Builder;

    /**
     * Extrahiert aus einem lokalen Modell den Wertesatz für den paarweisen/
     * unscharfen Vergleich (gleiche Schlüssel wie der gemappte Remote-Satz).
     *
     * @return array<string, mixed>
     */
    public function extract(Model $model): array;

    /**
     * Anzeige-Texte für die Inbox.
     *
     * @param  array<string, mixed>  $mapped
     * @return array{title: string, subtitle: ?string}
     */
    public function display(array $mapped): array;

    /**
     * Legt einen neuen lokalen Datensatz aus gemappten Feldern an
     * (nur bei Policy AutoLinkAndCreate).
     *
     * @param  array<string, mixed>  $mapped
     */
    public function create(Organization $organization, array $mapped): Model;
}
