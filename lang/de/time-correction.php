<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time-correction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Auftrag einer Zeitbuchung per Korrekturantrag ändern (MVP-1552).
    'order' => [
        'label' => 'Auftrag ändern',
        'unchanged' => '— unverändert —',
        'none' => '— ohne Auftrag —',
        'current' => 'aktuell',
    ],
    // Eigene Stempelung korrigieren, auch offline (MVP-1595).
    'attendance' => [
        'title' => 'Stempelung korrigieren',
        'action' => 'Stempelung korrigieren',
        'submit' => 'Korrektur senden',
        'intro' => 'Die Korrektur läuft als Antrag durch die Freigabe; erlaubt Ihre Organisation die Selbstkorrektur, gilt sie sofort. Ohne Verbindung wird sie auf dem Gerät gespeichert und beim nächsten Kontakt übertragen.',
        'span' => 'Beginn und Ende',
        'reason' => 'Begründung',
        'reason_placeholder' => 'Mindestens :min Zeichen, z. B. „vergessen auszustempeln“',
        'changed' => 'Die Stempelung wurde inzwischen geändert — bitte öffnen Sie den Dialog erneut.',
        'applied' => 'Stempelung korrigiert.',
        'submitted' => 'Korrektur zur Freigabe eingereicht.',
    ],
];
