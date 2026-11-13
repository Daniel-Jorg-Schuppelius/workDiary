<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : presence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669).
return [
    'board' => 'Aktuelle Belegung',
    'kind' => [
        'do_not_disturb' => 'Nicht stören',
        'brief_away' => 'Kurz weg',
        'out_of_office' => 'Außer Haus',
    ],
    'until' => ':kind bis :time',
    'validation' => [
        'until_required' => 'Für diesen Stand brauchen Sie ein Ende.',
        'until_range' => 'Das Ende muss in der Zukunft und höchstens :hours Stunden entfernt liegen.',
    ],
    'dialog' => [
        'title' => 'Mein Stand',
        'submit' => 'Stand setzen',
        'hint' => 'Gezeigt wird nur Ihr aktueller Stand – ohne Grund und ohne Verlauf. Er erlischt mit dem Ende, beim Ausstempeln oder wenn Sie ihn zurücksetzen.',
        'current' => 'Aktuell',
        'kind' => 'Stand',
        'duration' => 'Für',
        'no_duration' => 'Ohne Ende (bis zum Zurücksetzen oder Ausstempeln)',
        'until' => 'Oder genau bis',
        'until_hint' => 'Geht der Auswahl „Für“ vor. „Kurz weg“ und „Außer Haus“ brauchen ein Ende.',
    ],
    'duration' => [
        '15' => '15 Minuten',
        '30' => '30 Minuten',
        '60' => '1 Stunde',
        '120' => '2 Stunden',
        '240' => '4 Stunden',
        '480' => '8 Stunden',
    ],
    'action' => [
        'set' => 'Mein Stand',
        'clear' => 'Stand zurücksetzen',
    ],
    'flash' => [
        'set' => 'Ihr Stand „:status“ ist gesetzt.',
        'cleared' => 'Ihr Stand ist zurückgesetzt.',
    ],
];
