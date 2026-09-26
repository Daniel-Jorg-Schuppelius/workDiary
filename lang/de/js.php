<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : js.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Strings exposed to JavaScript via window.__translations.
 * Keys here are also accessible from JS via window.__('js.key') after
 * the JS i18n bridge has run. Keep this list lean.
 */

return [
    'dialog' => [
        'check_input' => 'Bitte Eingaben prüfen.',
        'save_failed' => 'Dialog konnte nicht gespeichert werden.',
        'load_failed' => 'Dialog konnte nicht geladen werden.',
        'loading' => 'Wird geladen …',
        'open_in_new_tab' => 'Seite in neuem Tab öffnen',
        'switch_to_new' => 'In den neuen Modus wechseln',
        'switch_to_legacy' => 'In den Legacy-Modus wechseln',
    ],
    'schedule' => [
        'move_failed' => 'Fehler beim Verschieben.',
        'suggest_failed' => 'Vorschläge konnten nicht geladen werden.',
    ],
    // Dienstplan-Oberflaeche (MVP-797): war zuvor fest verdrahtetes Deutsch.
    'schedule_ui' => [
        'shift_edit' => 'Schicht bearbeiten',
        'shift_create' => 'Schicht anlegen',
        'shift_delete_confirm' => 'Schicht wirklich löschen?',
        'shift_type_edit' => 'Schichttyp bearbeiten',
        'shift_type_create' => 'Neuen Schichttyp anlegen',
        'shift_type_delete_confirm' => 'Schichttyp wirklich löschen?',
        'save' => 'Speichern',
        'delete' => 'Löschen',
        'close' => 'Schließen',
        'save_failed' => 'Fehler beim Speichern.',
        'delete_failed' => 'Fehler beim Löschen.',
        'publish_failed' => 'Fehler beim Veröffentlichen.',
        'confirm_failed' => 'Fehler beim Bestätigen.',
        'suggestions_title' => 'Besetzungsvorschläge',
        'col_employee' => 'Mitarbeiter',
        'col_score' => 'Score',
        'col_reason' => 'Begründung',
        'type_active_yes' => 'ja',
        'type_active_no' => 'nein',
    ],
    'bulk' => [
        'select_one' => 'Bitte zuerst mindestens einen Eintrag auswählen.',
    ],
    'design' => [
        'inheritance' => '„:base“ · :inherited/:total geerbt, :own überschrieben',
    ],
    // Chat-Oberflaeche (MVP-798): angepinnte Nachrichten werden per JSON geladen.
    'chat' => [
        'pinned_failed' => 'Angepinnte Nachrichten konnten nicht geladen werden.',
        'pinned_empty' => 'Keine angepinnten Nachrichten.',
    ],
    'kanban' => [
        'invalid_move' => 'Dieser Statuswechsel ist im Auftragsworkflow nicht vorgesehen.',
        'not_allowed' => 'Keine Berechtigung für diese Auftragsaktion.',
        'handover_via_order' => 'Die Abnahme erfordert ein signiertes Protokoll und wird direkt im Auftrag ausgeführt.',
        'no_targets' => 'Für diese Karte gibt es aktuell keinen zulässigen Zug.',
    ],
    'entry_bar' => [
        'options_failed' => 'Aufgaben/Aufträge konnten nicht geladen werden.',
    ],
    'http' => [
        'session_expired' => 'Ihre Sitzung ist abgelaufen — die Seite wird neu geladen.',
    ],
    // KI-Tagvorschläge im Tag-Picker (Feature 143, MVP-711)
    'ai' => [
        'tags_no_text' => 'Bitte zuerst einen Inhalt eingeben — die KI schlägt Tags aus dem Text vor.',
        'tags_none' => 'Kein bestehendes Tag passt zum Text.',
        'tags_failed' => 'KI-Tagvorschlag nicht möglich: :message',
        'tags_loading' => 'KI sucht passende Tags …',
    ],
    // Tastenkürzel-Übersicht (Feature 037, MVP-721): Labels der Registry resources/js/shortcuts.js
    'shortcuts' => [
        'help' => 'Kontexthilfe zur aktuellen Seite öffnen',
        'title' => 'Tastenkürzel',
        'scope' => [
            'global' => 'Global',
            'navigation' => 'Navigation',
            'search' => 'Suche',
        ],
        'search' => 'Globale Suche öffnen',
        'shortcuts' => 'Diese Übersicht anzeigen',
        'escape' => 'Dialog oder Suche schließen',
        'search_move' => 'In den Suchergebnissen bewegen',
        'search_open' => 'Treffer öffnen',
        'go_diary' => 'Zum Tagebuch',
        'go_customers' => 'Zu den Kunden',
        'go_projects' => 'Zu den Projekten',
        'new_entry' => 'Neuer Eintrag',
        'then' => 'dann',
    ],
    'quiz' => [
        'progress' => ':answered von :total beantwortet',
    ],
    // NFC-Scan (MVP-903).
    'nfc' => [
        'hold' => 'Tag an das Gerät halten …',
        'empty' => 'Der Tag enthält keinen Link.',
        'failed' => 'NFC nicht möglich.',
        'written' => 'Tag beschrieben.',
    ],
];
