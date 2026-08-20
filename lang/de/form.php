<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : form.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'templates' => 'Formularvorlagen',
        'template' => 'Vorlage',
        'submissions' => 'Formulare',
        'submission' => 'Ausgefülltes Formular',
        'values' => 'Eingaben',
        'panel' => 'Formulare',
    ],

    'subtitle' => [
        'templates' => 'Konfigurierbare Formulare (Protokolle, Checklisten) ohne Code pflegen.',
        'submissions' => 'Ausgefüllte Formulare — versionssicher über den Felddefinitions-Snapshot.',
    ],

    'field' => [
        'name' => 'Name',
        'valid_from' => 'Gültig ab',
        'valid_until' => 'Gültig bis',
        'target_entry_type' => 'Zuordnung: Auftragstyp',
        'target_customer' => 'Zuordnung: Kunde',
        'description' => 'Beschreibung',
        'status' => 'Status',
        'fields' => 'Felder',
        'submissions' => 'Ausgefüllt',
        'creator' => 'Erstellt von',
        'template' => 'Vorlage',
        'subject' => 'Bezug',
        'submitted_by' => 'Ausgefüllt von',
        'submitted_at' => 'Ausgefüllt am',
        'field_label' => 'Feldbezeichnung',
        'field_type' => 'Feldtyp',
        'field_required' => 'Pflicht',
        'field_options' => 'Optionen',
        'field_help' => 'Hilfetext',
        'field_unit' => 'Einheit',
    ],

    'action' => [
        'create_template' => 'Vorlage anlegen',
        'edit' => 'Bearbeiten',
        'save' => 'Speichern',
        'activate' => 'Aktivieren',
        'archive' => 'Archivieren',
        'delete' => 'Löschen',
        'add_field' => 'Feld hinzufügen',
        'remove_field' => 'Feld entfernen',
        'fill' => 'Formular ausfüllen',
        'submit' => 'Absenden',
        'show' => 'Ansehen',
        'print' => 'Drucken',
        'download_pdf' => 'PDF herunterladen',
        'clear_signature' => 'Signatur leeren',
        'back' => 'Zurück',
    ],

    'filter' => [
        'all' => 'Alle',
        'search' => 'Suche',
        'search_placeholder' => 'Vorlagenname durchsuchen',
        'period' => 'Zeitraum',
    ],

    'hint' => [
        'options' => 'Komma-getrennt, z. B. gut, mittel, schlecht',
        'unit' => 'z. B. kWh, °C, Stück',
    ],

    'subject_kind' => [
        'diary' => 'Auftrag',
        'customer' => 'Kunde',
        'asset' => 'Asset',
        'project' => 'Projekt',
    ],

    'value' => [
        'yes' => 'Ja',
        'no' => 'Nein',
        'signed' => 'Unterschrieben',
    ],

    'condition' => [
        'legend' => 'Sichtbar wenn',
        'always' => '— immer sichtbar —',
        'value_placeholder' => 'Vergleichswert',
        'op' => [
            'eq' => 'gleich',
            'ne' => 'ungleich',
            'in' => 'einer von (Komma)',
            'filled' => 'ausgefüllt',
        ],
    ],

    // Offline erfasste Anhänge (Audit 2026-08, W4.1).

    'attachment' => ['pending' => 'Wird nachgeladen'],

    'validation' => [

        'no_upload_field' => 'Für diesen Feld-Schlüssel gibt es kein Datei-/Fotofeld im Formular.',
        'invalid_row' => 'Felddefinition in Zeile :row ist ungültig.',
        'label_required' => 'Feld :row braucht eine Bezeichnung (max. 160 Zeichen).',
        'unknown_type' => 'Feld :row hat einen unbekannten Feldtyp.',
        'invalid_key' => 'Feld-Schlüssel „:key" ist ungültig (Kleinbuchstaben, Ziffern, Unterstriche).',
        'duplicate_key' => 'Feld-Schlüssel „:key" ist doppelt vergeben.',
        'select_needs_options' => 'Auswahlfeld „:label" braucht mindestens eine Option.',
        'fields_required' => 'Die Vorlage braucht mindestens ein Feld.',
        'too_many_fields' => 'Maximal :max Felder je Vorlage.',
        'template_not_active' => 'Diese Vorlage ist nicht aktiv und kann nicht ausgefüllt werden.',
        'condition_unknown_field' => 'Bedingung von Feld „:label" verweist auf ein unbekanntes Feld „:field".',
        'condition_cycle' => 'Bedingungen bilden einen Zyklus (Feld „:field" hängt indirekt von sich selbst ab).',
    ],

    'flash' => [
        'template_created' => 'Vorlage wurde angelegt.',
        'template_updated' => 'Vorlage wurde aktualisiert.',
        'template_activated' => 'Vorlage wurde aktiviert.',
        'template_archived' => 'Vorlage wurde archiviert.',
        'template_deleted' => 'Vorlage wurde gelöscht.',
        'submitted' => 'Formular wurde gespeichert.',
    ],

    'empty_templates_title' => 'Keine Vorlagen gefunden',
    'empty_templates' => 'Noch keine Formularvorlagen vorhanden.',
    'empty_submissions_title' => 'Keine Formulare gefunden',
    'empty_submissions' => 'Noch keine ausgefüllten Formulare vorhanden.',
    'empty_filtered' => 'Für die aktuellen Filter wurden keine Einträge gefunden.',
    'empty_panel' => 'Noch keine Formulare zu diesem Eintrag ausgefüllt.',
    'confirm_archive' => 'Vorlage wirklich archivieren? Sie fällt damit aus der Ausfüll-Auswahl.',
    'confirm_delete' => 'Vorlage wirklich löschen? Ausgefüllte Formulare bleiben erhalten.',
];
