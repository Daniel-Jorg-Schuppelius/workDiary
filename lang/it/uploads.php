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
    'label' => 'File',
    'hint' => 'Consentiti: :formats · fino a :size per file · al massimo :count file, :total in totale.',
    'progress' => 'Caricamento … :percent %',
    'error' => [
        'type' => ':name: tipo di file non consentito.',
        'too_large' => ':name supera :size.',
        'count' => 'Selezioni al massimo :count file alla volta.',
        'total' => 'I file sono troppo grandi nel complesso (al massimo :size per invio).',
        'failed' => 'Il caricamento non è riuscito. I dati non sono stati salvati: riprovi.',
    ],
];
