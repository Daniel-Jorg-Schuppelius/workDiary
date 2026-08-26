<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hr.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Digitale Personalakte (Feature 141, MVP-708).
    'personnel_file' => [
        'title' => 'Personalakte',
        'title_mine' => 'Meine Personalakte',
        'nav' => 'Meine Personalakte',
        'subtitle' => 'Personalakte von :name — vertraulich, sichtbar nur für den Personalakten-Kreis und die betroffene Person.',
        'subtitle_mine' => 'Ihre eigene Personalakte (Eigenauskunft, nur lesend).',
        'back' => 'Zur Mitarbeiterliste',
        'empty' => 'Noch keine Dokumente in der Personalakte.',
        'confidential_fixed' => 'Personalakten sind immer vertraulich — der Schalter entfällt, das Merkmal wird erzwungen.',
        'retention_pending' => 'ab Austritt',
        'confirm_delete' => 'Dokument endgültig aus der Personalakte vernichten? Dateien und Versionen werden gelöscht; das Audit-Protokoll bleibt.',
        'field' => [
            'title' => 'Titel',
            'category' => 'Kategorie',
            'validity' => 'Gültigkeit',
            'valid_from' => 'Gültig ab',
            'valid_until' => 'Gültig bis',
            'retention_until' => 'Aufbewahrung bis',
            'version' => 'Version',
            'updated_at' => 'Aktualisiert',
            'description' => 'Beschreibung',
            'file' => 'Datei',
            'version_note' => 'Versionshinweis',
            'documents' => 'Dokumente',
        ],
        'action' => [
            'upload' => 'Dokument aufnehmen',
            'edit' => 'Bearbeiten',
            'save' => 'Speichern',
            'download' => 'Herunterladen',
            'versions' => 'Versionen',
            'delete' => 'Vernichten',
        ],
        'flash' => [
            'created' => 'Dokument wurde in die Personalakte aufgenommen.',
            'updated' => 'Personalakten-Dokument wurde aktualisiert.',
        ],
    ],
];
