<?php
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
    ],
    'kanban' => [
        'invalid_move' => 'Dieser Statuswechsel ist im Auftragsworkflow nicht vorgesehen.',
        'not_allowed' => 'Keine Berechtigung für diese Auftragsaktion.',
        'handover_via_order' => 'Die Abnahme erfordert ein signiertes Protokoll und wird direkt im Auftrag ausgeführt.',
    ],
    'entry_bar' => [
        'options_failed' => 'Aufgaben/Aufträge konnten nicht geladen werden.',
    ],
];
