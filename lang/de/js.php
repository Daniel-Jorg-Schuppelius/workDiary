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
        'loading' => 'Lade…',
        'open_in_new_tab' => 'Seite in neuem Tab öffnen',
        'switch_to_new' => 'In den neuen Modus wechseln',
        'switch_to_legacy' => 'In den Legacy-Modus wechseln',
    ],
    'schedule' => [
        'move_failed' => 'Fehler beim Verschieben.',
        'suggest_failed' => 'Vorschläge konnten nicht geladen werden.',
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
        'session_expired' => 'Deine Sitzung ist abgelaufen — die Seite wird neu geladen.',
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
];
