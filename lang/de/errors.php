<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : errors.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'csv' => [
        'unreadable' => 'Datei nicht lesbar.',
        'header_missing' => 'Kopfzeile fehlt oder unlesbar: :error',
        'name_column_missing' => 'Pflichtspalte "Name" nicht gefunden.',
    ],
    'routing' => [
        'nominatim_missing_coords' => 'Nominatim-Antwort enthält keine Koordinaten.',
        'nominatim_http' => 'Nominatim lieferte HTTP :status zurück.',
    ],
    'upload' => [
        'too_large' => 'Datei ist zu groß (max. :max KB).',
        'type_not_allowed' => 'Dateityp nicht erlaubt.',
    ],

    // HTTP-Fehlerseiten (041-P0, MVP-053)
    'request_id' => 'Vorgangs-ID',
    'report_problem' => 'Problem melden',
    '404' => [
        'title' => 'Seite nicht gefunden',
        'message' => 'Die aufgerufene Seite existiert nicht oder wurde verschoben.',
    ],
    '403' => [
        'title' => 'Kein Zugriff',
        'message' => 'Für diese Aktion fehlt Ihnen die Berechtigung. Bitte wenden Sie sich an Ihre Administration.',
    ],
    '419' => [
        'title' => 'Sitzung abgelaufen',
        'message' => 'Die Seite war zu lange geöffnet. Bitte laden Sie sie neu und versuchen Sie es erneut.',
    ],
    '500' => [
        'title' => 'Interner Fehler',
        'message' => 'Es ist ein unerwarteter Fehler aufgetreten. Bitte versuchen Sie es später erneut oder melden Sie das Problem mit der Vorgangs-ID.',
    ],
];
