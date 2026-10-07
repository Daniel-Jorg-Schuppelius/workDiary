<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : uploads.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Datei-Uploads mit Zweck (MVP-1074): Hinweise, Fortschritt und Fehler je Datei.
 */

return [
    'label' => 'Dateien',
    'hint' => 'Erlaubt: :formats · bis :size je Datei · höchstens :count Dateien, zusammen bis :total.',
    'progress' => 'Wird hochgeladen … :percent %',
    'error' => [
        'type' => ':name: Dateityp nicht erlaubt.',
        'too_large' => ':name ist größer als :size.',
        'count' => 'Bitte höchstens :count Dateien auf einmal auswählen.',
        'total' => 'Die Dateien sind zusammen zu groß (höchstens :size je Einreichung).',
        'failed' => 'Das Hochladen ist fehlgeschlagen. Ihre Eingaben sind nicht gespeichert — bitte erneut versuchen.',
    ],
];
